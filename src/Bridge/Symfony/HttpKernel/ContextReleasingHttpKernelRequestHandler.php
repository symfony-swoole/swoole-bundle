<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\HttpKernel;

use K911\Swoole\Bridge\Symfony\Container\CoWrapper;
use K911\Swoole\Server\RequestHandler\RequestHandlerInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;

final class ContextReleasingHttpKernelRequestHandler implements RequestHandlerInterface
{
    private RequestHandlerInterface $decorated;
    private CoWrapper $coWrapper;

    public function __construct(RequestHandlerInterface $decorated, CoWrapper $coWrapper)
    {
        $this->decorated = $decorated;
        $this->coWrapper = $coWrapper;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function handle(SwooleRequest $request, SwooleResponse $response): void
    {
        $this->coWrapper->defer();
        $this->decorated->handle($request, $response);
    }
}
