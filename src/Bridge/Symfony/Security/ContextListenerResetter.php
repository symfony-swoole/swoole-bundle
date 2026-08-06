<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Security;

use Assert\Assertion;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;
use Symfony\Component\Security\Http\Firewall\ContextListener;

/**
 * Puts a pooled ContextListener back to not having registered its response listener.
 *
 * The listener registers itself on the firewall's dispatcher the first time it authenticates a request,
 * and takes itself off again in onKernelResponse(). Between the two it remembers having done so in a flag,
 * and that flag does not always get cleared: onKernelResponse() returns before clearing it when the request
 * has no session, or when the firewall that ran was not the one this listener belongs to - neither of which
 * stops authenticate() from having set it.
 *
 * Written for a listener that is thrown away with the request, that is harmless. Pooled, the flag is
 * carried into every later request the instance serves, and a listener that believes it is registered
 * never registers again - so nothing writes the security token back to the session, and the user is
 * quietly logged out on every request that instance happens to serve.
 *
 * There is no method to ask for this, so the property is written directly.
 */
final class ContextListenerResetter implements Resetter
{
    private ?ReflectionProperty $registeredProperty = null;

    public function reset(object $service): void
    {
        Assertion::isInstanceOf($service, ContextListener::class);

        $this->registeredProperty()->setValue($service, false);
    }

    private function registeredProperty(): ReflectionProperty
    {
        return $this->registeredProperty ??= new ReflectionProperty(ContextListener::class, 'registered');
    }
}
