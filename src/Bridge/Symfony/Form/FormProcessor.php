<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Form;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\ServiceProxifier;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\Extension\PasswordHasher\EventListener\PasswordHasherListener;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;

/**
 * Gives every coroutine its own form renderer, and a way back for the one it hands in.
 *
 * A render walks down the form tree and back up again, and the renderer keeps its place in that walk
 * on itself - the variable scope of the view being rendered, the block hierarchy of the current
 * suffix, and how far down that hierarchy the search has got:
 *
 * ```php
 * $this->variableStack[$viewCacheKey][] = $variables;
 * $html = $this->engine->renderBlock($view, $resource, $blockName, $variables);
 * array_pop($this->variableStack[$viewCacheKey]);
 * ```
 *
 * Every block of every form goes through there, so one shared renderer puts two concurrent form pages
 * on the same stack - and the one that renders a block while the other is between its push and its
 * pop reads the other request's variables.
 *
 * Which is the loudest of the failures a form-rendering application sees under any real concurrency:
 * a burst of them per minute, and a 500 on every page with a form on it.
 *
 * The engine underneath it - the other half of the pair, and just as per-request - is pooled already,
 * because AbstractRendererEngine implements ResetInterface and Symfony therefore tags it
 * `kernel.reset`. The renderer has no reset() and no tag, so nothing picked it up, and pooling half a
 * pair fixes nothing. Saying so here is what makes it poolable at all, the same way
 * {@see \SwooleBundle\SwooleBundle\Bridge\Symfony\Twig\TwigProcessor} does for the twig profile.
 *
 * The renderer is reached as a Twig runtime rather than injected anywhere, which is what makes the
 * pool work without touching a single template: `RuntimeLoaderPass` keys the runtime locator by the
 * definition's class, the proxy definition keeps the class of the service it replaces, and
 * `getRuntime()` therefore still resolves `Symfony\Component\Form\FormRenderer` - to the proxy, which
 * resolves per coroutine. Its resetter is not optional - see {@see FormRendererResetter} for what a
 * half-finished render leaves behind.
 *
 * The engine then needs the other half of the same treatment: being pooled is not enough for it,
 * because the reset it comes with leaves behind the resolved form theme - and with it the Environment
 * that resolved it - for the next coroutine to render every block through. That is where the profiler
 * extension's $stackLevel kept being written across coroutines long after the extensions themselves
 * stopped being shared. See {@see TwigRendererEngineResetter}.
 *
 * Validating a form is the same defect one layer down, and it is the constraint validator factory that
 * has to be pooled to fix it. FormValidator writes the context of the validation it is running onto
 * itself - `ConstraintValidator::initialize()` is `$this->context = $context` and nothing else - and
 * the instance it writes to is not one this bundle can reach: it is not a service, it is
 * `new $name()` inside {@see \Symfony\Component\Validator\ContainerConstraintValidatorFactory} and
 * kept in a memo there for the life of the process. Two concurrent form submissions therefore share
 * one FormValidator, and a coroutine that suspends between its initialize() and its validate() -
 * which a validator doing any I/O does - comes back to the other request's context:
 *
 *   FiberViber\ConcurrencyException: Cross-coroutine access detected: [property_write]
 *   FormValidator::$context is owned by coroutine #14 but accessed by coroutine #15
 *
 * Pooling the factory gives each coroutine its own memo and so its own validators. Nothing is reached
 * through the container here, so nothing else could have been pooled instead - and no resetter is
 * needed, since the memo is one instance per constraint class whoever asks, and every validation
 * writes the context it is about to use before reading it.
 *
 * The validators an application does register as services - `doctrine.orm.validator.unique` being the
 * one Symfony ships - are a hole this does not close: the factory memoizes whatever the container
 * hands it, so those stay as shared as the container makes them. They carry the same $context write,
 * and pooling them belongs with whatever pools the validator layer rather than here.
 */
final class FormProcessor implements CompileProcessor
{
    private const string RENDERER_ID = 'twig.form.renderer';
    private const string RENDERER_RESETTER_ID = 'swoole_bundle.form.renderer_resetter';
    private const string ENGINE_ID = 'twig.form.engine';
    private const string ENGINE_RESETTER_ID = 'swoole_bundle.form.twig_renderer_engine_resetter';
    private const string ENGINE_INITIALIZER_ID = 'swoole_bundle.form.twig_renderer_engine_initializer';
    private const string ENVIRONMENT_ID = 'twig';
    private const string VALIDATOR_FACTORY_ID = 'validator.validator_factory';
    private const string CONSTRAINT_VALIDATOR_TAG = 'validator.constraint_validator';
    private const string PASSWORD_HASHER_LISTENER_ID = 'form.listener.password_hasher';

    public function process(ContainerBuilder $container, ServiceProxifier $proxifier): void
    {
        $this->poolRenderer($container);
        $this->replaceEngineResetter($container);
        $this->poolValidatorFactory($container);
        $this->poolConstraintValidatorServices($container);
        $this->poolPasswordHasherListener($container);
    }

    /**
     * Guarded on the interface rather than on Symfony's own class: an application is free to build its
     * validators some other way, and whatever it builds them with is what has to be per coroutine.
     * Applications without the validator component have no such service and nothing to pool.
     */
    private function poolValidatorFactory(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::VALIDATOR_FACTORY_ID)) {
            return;
        }

        $definition = $container->findDefinition(self::VALIDATOR_FACTORY_ID);
        $class = $definition->getClass();

        if ($class === null || !is_a($class, ConstraintValidatorFactoryInterface::class, true)) {
            return;
        }

        $definition->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);
    }

    /**
     * The validators that are services, pooled as well as the factory that hands them out.
     *
     * Pooling the factory gives each coroutine its own memo of validators it builds with `new`. A validator
     * that is a service - the email validator, which is configured; the one that asks whether a password has
     * leaked, which is given an http client; UniqueEntity's, which is given Doctrine; anything an application
     * registers - is not built by the factory at all: ContainerConstraintValidatorFactory fetches it from the
     * container, and every coroutine's factory gets the same instance back. Validating writes the execution
     * context onto that instance first (initialize()) and reads it back as it adds violations, so two coroutines
     * validating with one of them write each other's context:
     *
     *   FiberViber\ConcurrencyException: Cross-coroutine access detected: [property_write]
     *   Symfony\Component\Validator\Constraints\EmailValidator::$context is owned by coroutine #7 but
     *   accessed by coroutine #9
     *
     * With no fiber_viber watching, the one that yields between the two - the leaked-password check does, on its
     * http request - reports its violations into the other request's form.
     *
     * By tag rather than by name: `validator.constraint_validator` is what the validator component itself uses to
     * find them, so it is every one of them, the application's own included.
     */
    private function poolConstraintValidatorServices(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds(self::CONSTRAINT_VALIDATOR_TAG)) as $serviceId) {
            $definition = $container->getDefinition($serviceId);

            if ($definition->hasTag(ContainerConstants::TAG_STATEFUL_SERVICE)) {
                continue;
            }

            $definition->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);
        }
    }

    /**
     * The listener behind PasswordType's `hash_property_path`, which collects the passwords of a submission on
     * itself and hashes them onto the user once the root form has been submitted:
     *
     * ```php
     * // registerPassword(), for every password field with the option
     * $this->passwords[] = ['form' => ..., 'property_path' => ..., 'password' => ...];
     * // hashPasswords(), for every root form - with the option or without it
     * foreach ($this->passwords as $password) { ... $this->passwordHasher->hashPassword(...) ... }
     * $this->passwords = [];
     * ```
     *
     * One listener for every form of every request, and it clears its list on every root form's submit, so two
     * coroutines submitting forms at once write the same list -
     *
     *   FiberViber\ConcurrencyException: Cross-coroutine access detected: [property_write]
     *   Symfony\Component\Form\Extension\PasswordHasher\EventListener\PasswordHasherListener::$passwords is
     *   owned by coroutine #5 but accessed by coroutine #8
     *
     * - which is one request's password hashed onto another request's user, or cleared by it before its own
     * form got to hash it, and a user saved with no password at all.
     *
     * No resetter: hashPasswords() empties the list at the end of every root submission, so the most a
     * submission that failed half way can leave on a pooled instance is an entry the next one clears.
     */
    private function poolPasswordHasherListener(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::PASSWORD_HASHER_LISTENER_ID)) {
            return;
        }

        $definition = $container->getDefinition(self::PASSWORD_HASHER_LISTENER_ID);

        if ($definition->getClass() !== PasswordHasherListener::class) {
            return;
        }

        if ($definition->hasTag(ContainerConstants::TAG_STATEFUL_SERVICE)) {
            return;
        }

        $definition->addTag(ContainerConstants::TAG_STATEFUL_SERVICE);
    }

    private function poolRenderer(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::RENDERER_ID)) {
            return;
        }

        $this->registerResetter($container, self::RENDERER_RESETTER_ID, FormRendererResetter::class);

        // Tagging rather than calling proxifyService(): the tag is what StatefulServicesPass acts on
        // once every compile processor has run, and doing both is refused outright by the Proxifier.
        $container->findDefinition(self::RENDERER_ID)
            ->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, [
                'resetter' => self::RENDERER_RESETTER_ID,
            ]);
    }

    /**
     * The engine is already pooled without any help from here - AbstractRendererEngine implements
     * ResetInterface, so Symfony tags it `kernel.reset` and StatefulServicesPass picks it up with a
     * resetter that calls reset(). What it needs is a wider reset than the one that class offers, for
     * the two properties reset() leaves alone; {@see TwigRendererEngineResetter} calls it and then
     * undoes those as well.
     *
     * Only for the Twig engine, since the properties in question are its own. An application rendering
     * forms through an engine of its own keeps the resetter Symfony's tag already gave it.
     */
    private function replaceEngineResetter(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::ENGINE_ID)) {
            return;
        }

        $engineDefinition = $container->findDefinition(self::ENGINE_ID);
        $engineClass = $engineDefinition->getClass();

        if ($engineClass === null || !is_a($engineClass, TwigRendererEngine::class, true)) {
            return;
        }

        $this->registerResetter($container, self::ENGINE_RESETTER_ID, TwigRendererEngineResetter::class);

        $tag = ['resetter' => self::ENGINE_RESETTER_ID];
        $initializer = $this->registerEnvironmentInitializer($container);

        if ($initializer !== null) {
            $tag['initializer'] = $initializer;
        }

        $engineDefinition->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, $tag);
    }

    /**
     * The engine is handed the Environment of the coroutine it is assigned to, which from Twig 3.29 on is
     * the difference between a form rendering and a 500 - {@see TwigRendererEngineEnvironmentInitializer}
     * has the whole of why.
     *
     * Only where twig is going to be pooled, which is what the tags say: StatefulServicesPass proxifies
     * what carries `kernel.reset` or this bundle's own tag, and TwigExtension only adds the former while
     * `Environment::resetGlobals()` exists - deprecated in Twig 3.14 and gone in 4. An unpooled Environment
     * is one object for everybody, so there is no foreign one for the engine to be holding, and the
     * initializer would only be a reference to a pool nobody registered.
     *
     * The pool is named rather than looked up: `twig.swoole_coop.service_pool` is what the Proxifier
     * registers once every compile processor has run, so at this point in the compile it does not exist
     * yet. Left ignorable on top of the tag check, because what registers it is a later pass and nothing
     * here can promise it: a decorated twig, for one, is pooled under the inner service's id.
     */
    private function registerEnvironmentInitializer(ContainerBuilder $container): ?string
    {
        if (!$container->hasDefinition(self::ENVIRONMENT_ID)) {
            return null;
        }

        $environmentTags = $container->getDefinition(self::ENVIRONMENT_ID)->getTags();

        if (
            !isset($environmentTags['kernel.reset'])
            && !isset($environmentTags[ContainerConstants::TAG_STATEFUL_SERVICE])
        ) {
            return null;
        }

        if (!$container->hasDefinition(self::ENGINE_INITIALIZER_ID)) {
            $initializerDef = new Definition(TwigRendererEngineEnvironmentInitializer::class);
            $initializerDef->setPublic(false);
            $initializerDef->setArgument(0, new Reference(
                sprintf('%s.swoole_coop.service_pool', self::ENVIRONMENT_ID),
                ContainerInterface::IGNORE_ON_INVALID_REFERENCE,
            ));
            $container->setDefinition(self::ENGINE_INITIALIZER_ID, $initializerDef);
        }

        return self::ENGINE_INITIALIZER_ID;
    }

    /**
     * @param class-string $class
     */
    private function registerResetter(ContainerBuilder $container, string $serviceId, string $class): void
    {
        if ($container->hasDefinition($serviceId)) {
            return;
        }

        $resetterDef = new Definition($class);
        $resetterDef->setPublic(false);
        $container->setDefinition($serviceId, $resetterDef);
    }
}
