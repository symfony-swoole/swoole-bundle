<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandlerInterface;
use SwooleBundle\SwooleBundle\Server\Runtime\BootableInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class TrustAllProxiesRequestHandler implements RequestHandlerInterface, BootableInterface
{
    public const HEADER_X_FORWARDED_ALL = SymfonyRequest::HEADER_X_FORWARDED_FOR | SymfonyRequest::HEADER_X_FORWARDED_HOST | SymfonyRequest::HEADER_X_FORWARDED_PORT | SymfonyRequest::HEADER_X_FORWARDED_PROTO;

    public function __construct(
        private readonly RequestHandlerInterface $decorated,
        private bool $trustAllProxies = false
    ) {
    }

    public function boot(array $runtimeConfiguration = []): void
    {
        if (isset($runtimeConfiguration['trustAllProxies']) && true === $runtimeConfiguration['trustAllProxies']) {
            $this->trustAllProxies = true;
        }
    }

    public function trustAllProxies(): bool
    {
        return $this->trustAllProxies;
    }

    public function handle(SwooleRequest $request, SwooleResponse $response): void
    {
        if ($this->trustAllProxies()) {
            SymfonyRequest::setTrustedProxies(['127.0.0.1', $request->server['remote_addr']], self::HEADER_X_FORWARDED_ALL);
        }

        $this->decorated->handle($request, $response);
    }
}
