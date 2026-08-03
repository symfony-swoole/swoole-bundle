<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\RequestHandler;

use Assert\Assertion;
use Override;
use RuntimeException;
use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;
use SwooleBundle\SwooleBundle\Server\Runtime\Bootable;

/**
 * Throws before the request ever reaches the Symfony kernel.
 *
 * An exception thrown inside a controller never gets here - the kernel catches it and renders the error
 * page itself, so swoole-bundle's own error handling is not involved at all. Only a throwable escaping
 * the kernel reaches ExceptionRequestHandler, which routes it through SymfonyExceptionHandler and
 * ErrorResponder onto the pooled Symfony ErrorHandler. That is the path a cross-coroutine access
 * violation takes in a real application, and the one PooledErrorHandlerResetTest needs to exercise.
 *
 * This decorates the innermost handler (HttpKernelRequestHandler) so the throw happens inside
 * ExceptionRequestHandler's try block, exactly like a failure raised while handling the request.
 */
final readonly class ThrowingRequestHandler implements RequestHandler, Bootable
{
    public const string PATH = '/server-layer-throwable';

    public const string MESSAGE = 'Thrown before the kernel could catch it';

    public function __construct(
        private RequestHandler $decorated,
    ) {}

    /**
     * The decorated handler is registered as a `swoole_bundle.bootable_service`, so this decorator takes
     * its place in that collection and has to pass the call through.
     *
     * @param array<string, mixed> $runtimeConfiguration
     */
    #[Override]
    public function boot(array $runtimeConfiguration = []): void
    {
        Assertion::isInstanceOf($this->decorated, Bootable::class);

        $this->decorated->boot($runtimeConfiguration);
    }

    #[Override]
    public function handle(Request $request, Response $response): void
    {
        if (($request->server['request_uri'] ?? null) === self::PATH) {
            throw new RuntimeException(self::MESSAGE);
        }

        $this->decorated->handle($request, $response);
    }
}
