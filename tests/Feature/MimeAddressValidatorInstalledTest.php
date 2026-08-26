<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * That a worker gives each of its coroutines an email validator of its own.
 *
 * Symfony\Component\Mime\Address validates every address through a private static it fills itself on
 * the first Address anything constructs, and the validator it fills it with writes its verdict onto
 * itself. One process sending one mail at a time is fine; a worker with two coroutines addressing mail
 * at once has two verdicts written to one place, and the mail that goes missing is whichever lost.
 *
 * The unit tests pin down the pooling itself. What only a server can show is the part the arrangement
 * rests on: the pool is built in the master, before Server::start() forks anything, and what is under
 * test is that a **worker** - a different process, which never boots a server of its own - hands two of
 * its coroutines two different validators out of it.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Mime\MimeAddressValidatorInstaller
 */
final class MimeAddressValidatorInstalledTest extends ServerTestCase
{
    private const string ENVIRONMENT = 'coroutines';

    private const string ROUTE = '/mime/address-validator';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testAWorkerGivesEachCoroutineAValidatorOfItsOwn(): void
    {
        $envs = ['APP_ENV' => self::ENVIRONMENT];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
        ], $envs);

        $serverStart->setTimeout(30);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', self::port(), false);
            self::assertTrue($client->connect(self::connectTimeout(), waitIfNoConnection: true));

            $response = $client->send(self::ROUTE)['response'];

            self::assertSame(200, $response['statusCode']);

            $report = (string) $response['body'];

            self::assertStringContainsString('pooled=yes', $report, sprintf(
                'The worker is addressing mail through the validator symfony/mime builds for itself, '
                . 'which writes its verdict onto itself and is shared by every coroutine in the '
                . 'process. %s',
                $report,
            ));
            self::assertStringContainsString('distinct_instances=2', $report, sprintf(
                'Two coroutines were given one validator between them, so they write their verdicts '
                . 'over each other. %s',
                $report,
            ));
        });
    }
}
