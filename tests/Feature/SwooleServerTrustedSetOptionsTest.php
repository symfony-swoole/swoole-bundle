<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Symfony\Component\Process\Process;

/**
 * `--trusted-hosts` and `--trusted-proxies` used to be declared as single-value options while the code
 * reading them expected a list, so only the configured defaults - which are lists - ever got through.
 * Anything passed on the command line arrived as a string and aborted the command on an assertion,
 * before the server was started.
 *
 * Everything here goes through a real console process on purpose: argv parsing is where that broke,
 * and an ArrayInput hands an array to an option however it was declared, so a CommandTester would keep
 * passing with the option back the way it was.
 */
final class SwooleServerTrustedSetOptionsTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    /**
     * The daemonizing sibling of the cases below, which only has to get far enough to exit zero:
     * passing either option used to be enough to abort it before the port was ever bound.
     */
    public function testTheServerStartsInTheBackgroundWithTrustedSetsGivenOnTheCommandLine(): void
    {
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
            '--trusted-hosts=a.example,b.example',
            '--trusted-proxies=10.0.0.1',
            '--trusted-proxies=10.0.0.2',
        ]);

        $serverStart->setTimeout(self::coverageEnabled() ? 10 : 3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->serverStop();
    }

    /**
     * One comma separated value, the option repeated, and the two mixed all have to mean the same list.
     */
    public function testEveryShapeOfTheOptionReachesTheStartupTable(): void
    {
        $output = $this->runServerAndReadConfiguration([
            '--trusted-hosts=a.example,b.example',
            '--trusted-proxies=10.0.0.1,10.0.0.2',
            '--trusted-proxies=10.0.0.3',
        ]);

        self::assertStringContainsString('a.example, b.example', $output);
        self::assertStringContainsString('10.0.0.1, 10.0.0.2, 10.0.0.3', $output);
    }

    /**
     * A `*` among the proxies means "trust them all", which the table reports as that one entry rather
     * than as the list it came in.
     */
    public function testTrustingEveryProxyIsReportedAsSuch(): void
    {
        $output = $this->runServerAndReadConfiguration(['--trusted-proxies=*,10.0.0.1']);

        self::assertMatchesRegularExpression('/trusted_proxies\s+\*\s/', $output);
    }

    /**
     * Left alone, both still resolve from the configuration - which is the only way they could be set
     * before, and the one shape that used to work.
     */
    public function testTheConfiguredSetsAreUsedWhenTheOptionsAreLeftAlone(): void
    {
        $output = $this->runServerAndReadConfiguration([]);

        self::assertStringContainsString('localhost, 127.0.0.1', $output);
    }

    /**
     * Runs the server until it has printed the configuration table it resolved, and stops it again.
     *
     * @param array<int, string> $args
     */
    private function runServerAndReadConfiguration(array $args): string
    {
        $server = $this->createConsoleProcess([
            'swoole:server:run',
            '--host=localhost',
            sprintf('--port=%d', self::port()),
            ...$args,
        ]);

        $server->setTimeout(self::coverageEnabled() ? 30 : 10);
        $server->start();

        try {
            return $this->awaitConfigurationTable($server);
        } finally {
            $server->stop();
        }
    }

    private function awaitConfigurationTable(Process $server): string
    {
        $deadline = microtime(true) + self::connectTimeout();

        do {
            $output = $server->getOutput();

            // the last of the two rows under test, so the table is complete once it shows up
            if (str_contains($output, 'trusted_proxies')) {
                return $output;
            }

            usleep(50_000);
        } while (microtime(true) < $deadline && $server->isRunning());

        self::fail(sprintf(
            "The server never printed its configuration table.\nOutput: %s\nErrors: %s",
            $server->getOutput(),
            $server->getErrorOutput(),
        ));
    }
}
