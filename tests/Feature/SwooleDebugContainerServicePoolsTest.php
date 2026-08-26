<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * `debug:container` reads the dump FrameworkBundle writes at priority -255, which is taken before
 * StatefulServicesPass has proxified anything - so without DebugContainerRedumpPass none of what
 * follows is in its output.
 */
final class SwooleDebugContainerServicePoolsTest extends ServerTestCase
{
    public function testTheProxifiedServicesAreListed(): void
    {
        $output = $this->runDebugContainer(['--env=coroutines']);

        // the original definition the pool builds instances from, and the pool built beside it
        self::assertStringContainsString('twig.swoole_coop.wrapped', $output);
        self::assertStringContainsString('twig.swoole_coop.service_pool', $output);
        self::assertStringContainsString(
            'SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\DiServicePool',
            $output,
        );
    }

    /**
     * The unmanaged factories are only tagged while StatefulServicesPass runs, by MessengerProcessor,
     * so the tag itself does not exist yet at the moment the framework takes its dump.
     */
    public function testTheUnmanagedFactoriesCanBeQueriedByTheirTag(): void
    {
        $output = $this->runDebugContainer(['--env=coroutines', '--tag=swoole_bundle.unmanaged_factory']);

        self::assertStringContainsString('messenger.transport.doctrine.factory', $output);
        self::assertStringContainsString('createTransport', $output);
    }

    /**
     * Nothing is proxified without coroutine support, so the framework's own dump is already accurate
     * and the pass has to leave it alone rather than pay for a second dump of the whole container.
     */
    public function testTheDumpIsNotTouchedWithoutCoroutineSupport(): void
    {
        $output = $this->runDebugContainer([]);

        self::assertStringNotContainsString('swoole_coop', $output);
    }

    /**
     * @param array<int, string> $args
     */
    private function runDebugContainer(array $args): string
    {
        $command = $this->createConsoleProcess(array_merge(['debug:container', '--no-ansi'], $args));

        $command->setTimeout(self::coverageEnabled() ? 120 : 60);
        $command->run();

        $this->assertProcessSucceeded($command);

        return $command->getOutput();
    }
}
