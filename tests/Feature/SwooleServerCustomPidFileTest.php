<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerCustomPidFileTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testStartServerOnCustomPidFileLocation(): void
    {
        $pidFile = $this->generateNotExistingCustomPidFile();

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
            sprintf('--pid-file=%s', $pidFile),
        ]);

        self::assertFileDoesNotExist($pidFile);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($pidFile): void {
            $this->deferServerStop([sprintf('--pid-file=%s', $pidFile)]);

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect(waitIfNoConnection: true));

            $this->assertFileExists($pidFile);
            $this->assertIsNumeric(file_get_contents($pidFile));

            $this->assertHelloWorldRequestSucceeded($client);
        });
    }

    public function testTryToStartServerOnReadOnlyExistingPidFile(): void
    {
        $pidFile = $this->setUpExistingReadOnlyPidFile();

        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
            sprintf('--pid-file=%s', $pidFile),
        ]);

        self::assertFileExists($pidFile);
        self::assertFileIsNotWritable($pidFile);

        $serverStart->setTimeout(3);
        $serverStart->run();

        $this->assertProcessFailed($serverStart);
        self::assertStringContainsString('Could not create pid file', $serverStart->getErrorOutput());
    }

    private function generateNotExistingCustomPidFile(): string
    {
        $hash = bin2hex(random_bytes(8));

        return sprintf('%s/custom-pid-file-%s.pid', self::FIXTURE_RESOURCES_DIR, $hash);
    }

    private function setUpExistingReadOnlyPidFile(): string
    {
        $hash = bin2hex(random_bytes(8));
        $readOnlyFile = sprintf('%s/existing-readonly-pid-file-%s.pid', self::FIXTURE_RESOURCES_DIR, $hash);

        self::assertNotFalse(file_put_contents($readOnlyFile, (string) $this->unassignablePid()));
        self::assertTrue(chmod($readOnlyFile, 0o400));

        return $readOnlyFile;
    }

    /**
     * A pid that cannot belong to anything, so the server refuses to start over the read-only file
     * rather than over the pid inside it.
     *
     * The command checks whether an instance is already running before it ever looks at the file being
     * writable, and that check is Process::kill(pid, 0) on whatever the file holds. This file used to
     * hold -9999, which fails that test twice over: a negative pid asks about a whole process *group*
     * rather than one process, and 9999 is a perfectly ordinary number for a container to hand out. Two
     * zombies left behind by an earlier run were enough - the command reported "Swoole HTTP Server is
     * already running" and the assertion below never got its say.
     *
     * Pids stop at pid_max - 1, so pid_max itself is never assigned to anything.
     */
    private function unassignablePid(): int
    {
        $pidMax = @file_get_contents('/proc/sys/kernel/pid_max');

        if (is_string($pidMax) && (int) trim($pidMax) > 0) {
            return (int) trim($pidMax);
        }

        return 4_194_304; // the usual Linux pid_max, for systems not exposing it
    }
}
