<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\RequestHandler;

use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;

final readonly class CodeCoverageRequestHandler implements RequestHandler
{
    public function __construct(
        private RequestHandler $decorated,
        private CodeCoverageManager $codeCoverageManager,
    ) {}

    public function handle(Request $request, Response $response): void
    {
        $testName = sprintf('test_request_%s', bin2hex(random_bytes(8)));
        $this->codeCoverageManager->start($testName);

        $this->decorated->handle($request, $response);

        $this->codeCoverageManager->stop();
        $this->codeCoverageManager->finish($testName);
    }
}
