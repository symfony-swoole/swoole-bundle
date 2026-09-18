<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Form;

use Assert\Assertion;
use Closure;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Twig\Template;

/**
 * Hands a pooled form renderer engine back with its themes as names again, rather than as templates
 * belonging to the Environment that last rendered through it.
 *
 * The engine resolves a theme once and writes the resolved template back over the name it was given,
 * through a by-reference parameter:
 *
 * ```php
 * protected function loadResourcesFromTheme(string $cacheKey, mixed &$theme): void
 * {
 *     if (!$theme instanceof Template) {
 *         $theme = $this->environment->load($theme)->unwrap();
 *     }
 *     $this->template ??= $theme;
 * ```
 *
 * The reference the root view passes is `$this->defaultThemes[$i]` - `form_div_layout.html.twig` and
 * whatever else `twig.form.resources` lists - so after the first form the engine renders, those
 * entries are `Twig\Template` objects. And a Twig template holds the Environment it was compiled by,
 * in `Template::$env`.
 *
 * Symfony's own reset() empties the themes, the resolved resources and the hierarchy caches, but not
 * those two: `$defaultThemes` is a constructor argument that nothing was ever expected to overwrite,
 * and `$template` is assigned once with `??=` for the life of the instance. Which is fine where the
 * instance dies with the request, and is not here: the pooled engine goes back into the pool holding a
 * template bound to one coroutine's Environment, and every form block the next coroutine renders goes
 * through
 *
 *   $this->template->displayBlock($blockName, $context, $this->resources[$cacheKey]);
 *
 * on that foreign Environment. Twig then counts, loads and initialises on it while its real owner is
 * still rendering:
 *
 *   FiberViber\ConcurrencyException: Cross-coroutine access detected: [property_write_preinc]
 *   Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension::$stackLevel is owned by coroutine
 *   #3666 but accessed by coroutine #3668
 *   ... [property_fetch_w] Twig\Environment::$loadedTemplates ...
 *   ... [property_write] Twig\ExtensionSet::$runtimeInitialized ...
 *
 * Which is why unsharing the profiler extensions and cascading the Environment's ownership did not end
 * the $stackLevel failures on pages with forms on them: the write is not on the Environment this
 * coroutine took out of the pool, so no amount of releasing that one reaches it.
 *
 * Restoring a theme to its name rather than to some remembered original is what makes this need no
 * state of its own: a name is what the engine was configured with, and loading it again through the
 * next coroutine's Environment is exactly what has to happen. `Environment::load()` memoizes per
 * Environment, so the reload costs one array lookup once each pooled Environment has seen the theme.
 *
 * `$template` has to go with them. It is only ever assigned when it holds nothing, so leaving it
 * behind would keep the stale template - and the stale Environment - however carefully the themes
 * around it were restored. Being a non-nullable typed property, it is unset rather than nulled, which
 * puts it back in the uninitialized state `??=` tests for.
 *
 * ### The block chain path
 *
 * From symfony/twig-bridge 7.4.19 and 8.1.7, wherever Twig has `Twig\BlockChain` (3.29 and later), the
 * engine takes a different road and never touches `$defaultThemes` or `$template` at all. It composes
 * the themes into chains instead - and a chain is built with the engine's Environment, loads its
 * templates through it and reads its globals on every render:
 *
 * ```php
 * $inherited = $this->defaultChain ??= new BlockChain($this->environment, array_reverse($this->defaultThemes));
 * ```
 *
 * The same bug in a new property: the per-view `$chains` are cleared by Symfony's reset(), but
 * `$defaultChain` is memoized for the life of the instance, so a pooled engine would carry the first
 * coroutine's Environment into every form rendered after it. It is nulled here, which is what `??=`
 * rebuilds from. Both halves stay: which road an engine takes depends on the installed versions, and
 * the properties of the other one are simply left as they were.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Form\FormProcessor
 */
final class TwigRendererEngineResetter implements Resetter
{
    private ?ReflectionProperty $defaultThemesProperty = null;

    /**
     * False once it is known the installed engine has no block chain path to reset.
     */
    private ReflectionProperty|false|null $defaultChainProperty = null;

    /**
     * @var (Closure(TwigRendererEngine): void)|null
     */
    private ?Closure $templateUnsetter = null;

    public function reset(object $service): void
    {
        Assertion::isInstanceOf($service, TwigRendererEngine::class);

        // what Symfony resets on every request anyway: the per-view themes, resources and hierarchies
        $service->reset();

        $this->defaultThemesProperty()->setValue($service, array_map(
            static fn(mixed $theme): mixed => $theme instanceof Template ? $theme->getTemplateName() : $theme,
            $this->defaultThemes($service),
        ));

        ($this->templateUnsetter())($service);

        $this->defaultChainProperty()?->setValue($service, null);
    }

    /**
     * @return array<mixed>
     */
    private function defaultThemes(TwigRendererEngine $engine): array
    {
        /** @var array<mixed> $defaultThemes */
        $defaultThemes = $this->defaultThemesProperty()->getValue($engine);

        return $defaultThemes;
    }

    private function defaultThemesProperty(): ReflectionProperty
    {
        return $this->defaultThemesProperty ??= new ReflectionProperty(TwigRendererEngine::class, 'defaultThemes');
    }

    /**
     * Null where the installed twig-bridge predates the block chain path, which has nothing to reset.
     */
    private function defaultChainProperty(): ?ReflectionProperty
    {
        $this->defaultChainProperty ??= property_exists(TwigRendererEngine::class, 'defaultChain')
            ? new ReflectionProperty(TwigRendererEngine::class, 'defaultChain')
            : false;

        return $this->defaultChainProperty !== false ? $this->defaultChainProperty : null;
    }

    /**
     * Bound to the class rather than reached through reflection, because unsetting is the one thing
     * ReflectionProperty cannot do: it can write a typed property but not put it back to uninitialized.
     *
     * @return Closure(TwigRendererEngine): void
     */
    private function templateUnsetter(): Closure
    {
        /** @var Closure(TwigRendererEngine): void $unsetter */
        $unsetter = $this->templateUnsetter ??= Closure::bind(
            static function (TwigRendererEngine $engine): void {
                unset($engine->template);
            },
            null,
            TwigRendererEngine::class,
        );

        return $unsetter;
    }
}
