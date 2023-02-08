<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\RequestHandler;

use K911\Swoole\Server\RequestHandler\RequestHandlerInterface;
use K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use Swoole\Http\Request;
use Swoole\Http\Response;

final class CodeCoverageRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private RequestHandlerInterface $decorated,
        private CodeCoverageManager $codeCoverageManager
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, Response $response): void
    {
        $testName = sprintf('test_request_%s', bin2hex(random_bytes(8)));
        $this->codeCoverageManager->start($testName);

        $this->decorated->handle($request, $response);

        $this->codeCoverageManager->stop();
        $this->codeCoverageManager->finish($testName);
    }
}
