<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\HttpKernel;

use Exception;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;

final readonly class ContextReleasingHttpKernelRequestHandler implements RequestHandler
{
    public function __construct(
        private RequestHandler $decorated,
        private CoWrapper $coWrapper,
    ) {}

    /**
     * @throws Exception
     */
    public function handle(SwooleRequest $request, SwooleResponse $response): void
    {
        $this->coWrapper->defer();
        $this->decorated->handle($request, $response);
    }
}
