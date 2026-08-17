<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Form;

use Assert\Assertion;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;
use Symfony\Component\Form\FormRenderer;

/**
 * Empties the three stacks a form render leaves behind when it does not finish.
 *
 * In the happy path the renderer balances itself: renderBlock() pushes a scope onto
 * `$variableStack[$viewCacheKey]`, pops it after the engine returns, and unsets the key entirely when
 * it was the call that created it - and searchAndRenderBlock() does the same with
 * `$blockNameHierarchyMap` and `$hierarchyLevelMap`. None of it is wrapped in a finally, so a block
 * that throws - a missing block, a filter blowing up, a form theme that is not there - leaves the
 * scope on the stack.
 *
 * In a request-per-process world that costs nothing: the instance dies with the request that broke it.
 * In a worker it goes back into the pool, and the next render of a form with the same cache key takes
 * the `else` branch, resumes from `end($this->variableStack[$viewCacheKey])` and renders that form
 * with a dead request's variables. Which is why pooling alone is not the fix here, exactly as with
 * {@see \SwooleBundle\SwooleBundle\Bridge\Symfony\WebProfiler\ContentSecurityPolicyHandlerResetter}.
 *
 * Symfony treats all of this as per-request state on the other half of the pair already: the renderer
 * engine implements ResetInterface and clears its themes and resolved resources on every request. The
 * renderer itself was simply never given a reset() to call, since nothing but a long-running worker
 * needs one.
 *
 * Reflection because all three properties are private with no accessor.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Form\FormProcessor
 */
final class FormRendererResetter implements Resetter
{
    private const array PROPERTIES = ['variableStack', 'blockNameHierarchyMap', 'hierarchyLevelMap'];

    /**
     * @var array<string, ReflectionProperty>
     */
    private array $properties = [];

    public function reset(object $service): void
    {
        Assertion::isInstanceOf($service, FormRenderer::class);

        foreach (self::PROPERTIES as $property) {
            $this->property($property)->setValue($service, []);
        }
    }

    private function property(string $name): ReflectionProperty
    {
        return $this->properties[$name] ??= new ReflectionProperty(FormRenderer::class, $name);
    }
}
