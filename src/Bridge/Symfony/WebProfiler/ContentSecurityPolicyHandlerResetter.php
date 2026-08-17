<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\WebProfiler;

use Assert\Assertion;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;
use Symfony\Bundle\WebProfilerBundle\Csp\ContentSecurityPolicyHandler;

/**
 * Turns Content-Security-Policy back on for the next request the pooled handler serves.
 *
 * The handler has one piece of state and no way back from it:
 *
 * ```php
 * private bool $cspDisabled = false;
 *
 * public function disableCsp(): void
 * {
 *     $this->cspDisabled = true;
 * }
 * ```
 *
 * Nothing in Symfony sets it back, because nothing needs to - in a request-per-process world the
 * handler dies with the request that disabled it. In a worker it does not, and every profiler
 * controller action calls disableCsp(), toolbarStylesheetAction included. One toolbar request is
 * therefore enough to leave the handler disabled for good, and every response the worker serves
 * afterwards quietly loses its CSP headers.
 *
 * That makes pooling alone the wrong fix: it would confine the damage to one pooled instance instead
 * of ending it. Hence a resetter, and a reflection-based one, since the property is private and the
 * class offers no way to ask for it back.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\WebProfiler\WebProfilerProcessor
 */
final class ContentSecurityPolicyHandlerResetter implements Resetter
{
    private ?ReflectionProperty $cspDisabledProperty = null;

    public function reset(object $service): void
    {
        Assertion::isInstanceOf($service, ContentSecurityPolicyHandler::class);

        $this->cspDisabledProperty()->setValue($service, false);
    }

    private function cspDisabledProperty(): ReflectionProperty
    {
        return $this->cspDisabledProperty ??= new ReflectionProperty(
            ContentSecurityPolicyHandler::class,
            'cspDisabled',
        );
    }
}
