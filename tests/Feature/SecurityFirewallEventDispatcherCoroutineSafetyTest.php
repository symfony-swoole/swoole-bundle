<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * Regression coverage for EventDispatcherProcessor's handling of firewall-scoped event dispatchers
 * (security.event_dispatcher.<name>, one per Symfony SecurityBundle firewall — see
 * Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension::createFirewall()). Before that fix,
 * only the app-wide event_dispatcher was made coroutine-safe: each firewall dispatcher stayed a shared
 * singleton, so concurrent coroutines serving requests through the same firewall could collide on it.
 *
 * The coroutines_security environment configures two bare firewalls (see its security.php) and lets
 * Symfony build their security.event_dispatcher.<name> definitions entirely on its own — decorated by a
 * TraceableEventDispatcher when kernel.debug is on (see MakeFirewallsEventDispatcherTraceablePass), plain
 * otherwise. APP_DEBUG toggles between those two shapes, mirroring how coroutineTestDataProviderForTaskWorkers
 * in SwooleServerCoroutinesTest varies debug on/off against the same environment rather than duplicating
 * environments by hand.
 *
 * The check itself runs a fixture console command that resolves each firewall's dispatcher from the real,
 * fully compiled container and reports whether it was proxified (i.e. made coroutine-safe) and is still
 * functionally usable — the same "is it proxified, does it still work" pattern already used by
 * OptionalStatefulServicesProxificationTest for the slugger service.
 */
final class SecurityFirewallEventDispatcherCoroutineSafetyTest extends ServerTestCase
{
    /**
     * @param array{APP_ENV: string, APP_DEBUG: string, WORKER_COUNT: string, REACTOR_COUNT: string} $envs
     */
    #[DataProvider('debugToggleDataProvider')]
    public function testEachFirewallDispatcherIsProxifiedAndStillWorks(array $envs): void
    {
        $process = $this->createConsoleProcess(['test:security-event-dispatcher:proxy-check'], $envs);
        $process->setTimeout(self::coverageEnabled() ? 30 : 15);
        $process->run();

        $this->assertProcessSucceeded($process);

        $output = $process->getOutput();

        self::assertStringContainsString('main IS proxified.', $output);
        self::assertStringContainsString('api IS proxified.', $output);
        self::assertStringContainsString('main dispatch WORKS.', $output);
        self::assertStringContainsString('api dispatch WORKS.', $output);
    }

    /**
     * @return array<string, array<array{
     *   APP_ENV: string,
     *   APP_DEBUG: string,
     *   WORKER_COUNT: string,
     *   REACTOR_COUNT: string,
     * }>>
     */
    public static function debugToggleDataProvider(): array
    {
        return [
            // decorated by a TraceableEventDispatcher, since kernel.debug is on
            'debug_on' => [
                ['APP_ENV' => 'coroutines_security', 'APP_DEBUG' => '1', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1'],
            ],
            // plain dispatcher, no decorator — kernel.debug is off
            'debug_off' => [
                ['APP_ENV' => 'coroutines_security', 'APP_DEBUG' => '0', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1'],
            ],
        ];
    }
}
