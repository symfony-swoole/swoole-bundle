<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\ErrorHandler;

use Assert\Assertion;
use Override;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;
use Symfony\Component\ErrorHandler\ErrorHandler;

/**
 * Clears the exception handler an errored request left behind on a pooled ErrorHandler instance.
 *
 * ErrorHandler::handleException() parks `[$this, 'renderException']` in $exceptionHandler and never
 * takes it back out again. renderException() is private, so that array is only callable from inside
 * ErrorHandler itself. The next request to reuse the pooled instance goes through the generated proxy,
 * whose setExceptionHandler() override declares the inherited `?callable` return type - and PHP checks
 * that return type in the proxy subclass' scope, where a private method of the parent is not callable.
 * The leftover array therefore blows up as
 * "Return value must be of type ?callable, array returned", turning every error after the first one
 * into an uncatchable fatal that hides whatever actually went wrong.
 *
 * Resetting is done on the real instance rather than through the proxy, so the same return type is
 * validated in ErrorHandler's own scope, where the array is perfectly callable.
 */
final readonly class ErrorHandlerResetter implements Resetter
{
    #[Override]
    public function reset(object $service): void
    {
        Assertion::isInstanceOf($service, ErrorHandler::class);

        $service->setExceptionHandler(null);
    }
}
