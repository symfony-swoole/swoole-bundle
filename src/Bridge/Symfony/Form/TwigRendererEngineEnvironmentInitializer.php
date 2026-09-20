<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Form;

use Assert\Assertion;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Initializer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePool;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Twig\Environment;

/**
 * Hands a pooled form renderer engine the Environment of the coroutine it is being handed to.
 *
 * The engine is built by the container, which resolves `twig` to the proxy standing in for the pool, so
 * every instance of it holds that one object rather than an Environment. Nothing minded while a template
 * was only ever asked to render: the proxy forwards the call to the coroutine's own Environment and the
 * answer is the same either way.
 *
 * Twig 3.29 minds. A `Twig\TemplateWrapper` now remembers the Environment that loaded it and refuses to be
 * used with another:
 *
 * ```php
 * public function isOwnedBy(Environment $env): bool
 * {
 *     return $this->env === $env && $this->template->isOwnedBy($env);
 * }
 * ```
 *
 * From symfony/twig-bridge 7.4.19 and 8.1.7 the engine renders every form through a `Twig\BlockChain`,
 * which it builds with the Environment it was given and fills by loading the themes through it:
 *
 * ```php
 * $inherited = $this->defaultChain ??= new BlockChain($this->environment, array_reverse($this->defaultThemes));
 * ```
 *
 * The load runs on the coroutine's Environment, because that is what the proxy forwards to, and the chain
 * is built with the proxy - two objects, and the check is an identity one. So the chain's constructor
 * throws `A block chain cannot contain templates from different Twig environments.` and every page with a
 * form on it answers 500, on the first request, with nothing concurrent about it.
 *
 * Binding at construction would only move the fault: a pooled instance outlives the coroutine it was built
 * for and is handed to the next one that asks, which by then has an Environment of its own. Binding here is
 * what matches the lifetime: the pool calls this as the instance is assigned, whether it was just built or
 * taken back out of the free pool, so the engine holds the Environment of whoever is about to render
 * through it - see BaseServicePool::getServiceToAssign().
 *
 * What the engine had cached against the Environment before is gone by then:
 * {@see TwigRendererEngineResetter} empties the chains, the resolved themes and the resources when the
 * instance is released, which is the release this assignment follows.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Form\FormProcessor
 */
final class TwigRendererEngineEnvironmentInitializer implements Initializer
{
    private ?ReflectionProperty $environmentProperty = null;

    /**
     * @param ServicePool<Environment>|null $environmentPool null where nothing registered a pool for the
     *        Environment after all, which leaves one object for everybody and nothing to rebind.
     */
    public function __construct(
        private readonly ?ServicePool $environmentPool = null,
    ) {}

    public function initialize(object $service): void
    {
        if ($this->environmentPool === null) {
            return;
        }

        Assertion::isInstanceOf($service, TwigRendererEngine::class);

        $environment = $this->environmentPool->get();
        $property = $this->environmentProperty();

        // Nothing to do for the coroutine that already holds this instance, which is every render after
        // the first: get() answers with what it assigned, and the engine was bound to it then.
        if ($property->getValue($service) === $environment) {
            return;
        }

        $property->setValue($service, $environment);
    }

    private function environmentProperty(): ReflectionProperty
    {
        return $this->environmentProperty ??= new ReflectionProperty(TwigRendererEngine::class, 'environment');
    }
}
