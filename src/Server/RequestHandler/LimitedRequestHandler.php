<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\RequestHandler;

use Assert\AssertionFailedException;
use InvalidArgumentException;
use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleBundle\SwooleBundle\Component\AtomicCounter;
use SwooleBundle\SwooleBundle\Server\HttpServer;
use SwooleBundle\SwooleBundle\Server\Runtime\Bootable;
use Symfony\Component\Console\Style\SymfonyStyle;

final class LimitedRequestHandler implements RequestHandler, Bootable
{
    private int $requestLimit = -1;

    private ?SymfonyStyle $symfonyStyle = null;

    public function __construct(
        private readonly RequestHandler $decorated,
        private readonly HttpServer $server,
        private readonly AtomicCounter $requestCounter,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function boot(array $runtimeConfiguration = []): void
    {
        $this->requestLimit = (int) ($runtimeConfiguration['requestLimit'] ?? -1);
        $this->symfonyStyle = $runtimeConfiguration['symfonyStyle'] ?? null;
    }

    /**
     * @throws AssertionFailedException
     */
    public function handle(Request $request, Response $response): void
    {
        $this->decorated->handle($request, $response);

        if ($this->requestLimit <= 0) {
            return;
        }

        $this->requestCounter->increment();

        $requestNo = $this->requestCounter->get();
        if ($requestNo === 1) {
            $this->console(static function (SymfonyStyle $io): void {
                $io->success('First response has been sent!');
            });
        }

        if ($this->requestLimit !== $this->requestCounter->get()) {
            return;
        }

        $this->console(static function (SymfonyStyle $io): void {
            $io->caution([
                'Request limit has been hit!',
                'Stopping server..',
            ]);
        });

        $this->server->shutdown();
    }

    private function console(callable $callback): void
    {
        if (!$this->symfonyStyle instanceof SymfonyStyle) {
            throw new InvalidArgumentException(
                'To interact with console, SymfonyStyle object must be provided as "symfonyStyle" attribute.'
            );
        }

        $callback($this->symfonyStyle);
    }
}
