<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * Regression coverage for SecurityProcessor's handling of SecurityBundle's access decision manager.
 *
 * AccessDecisionManager keeps the decision currently being made on a stack of its own, pushed on the way
 * into decide() and popped on the way out. The service is shared, so one stack ends up serving every
 * coroutine in the worker - and a voter only has to do any I/O for its coroutine to be suspended half way
 * through a decision, leaving the stack non-empty while another request walks into decide().
 *
 * The two debug modes are two different shapes to fix, which is why both are exercised here rather than
 * only the one a developer sees:
 *
 * - debug on: TraceableAccessDecisionManager decorates the real manager. The decorator is resettable, so
 *   it was pooled all along - but the manager holding the stack sat shared underneath it.
 * - debug off (production): there is no decorator, so the manager holding the stack is the service
 *   everything talks to, and it was not pooled at all.
 *
 * The check runs a fixture console command against the real, fully compiled container, the same
 * "is it proxified, does it still work" pattern SecurityFirewallEventDispatcherCoroutineSafetyTest and
 * OptionalStatefulServicesProxificationTest use.
 */
final class AccessDecisionManagerCoroutineSafetyTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // What is asserted here is a property of the compiled container, and a container compiled before
        // this processor existed answers just as happily from the cache - it took a stale one passing
        // this test with the processor removed to notice. Compiling from scratch is the only way the
        // answer is about the code under test.
        $this->deleteVarDirectory();
    }

    /**
     * @param array{APP_ENV: string, APP_DEBUG: string, WORKER_COUNT: string, REACTOR_COUNT: string} $envs
     */
    #[DataProvider('debugToggleDataProvider')]
    public function testTheAccessDecisionManagerIsProxifiedAndStillDecides(array $envs): void
    {
        $process = $this->createConsoleProcess(['test:access-decision-manager:proxy-check'], $envs);
        $process->setTimeout(self::coverageEnabled() ? 30 : 15);
        $process->run();

        $this->assertProcessSucceeded($process);

        $output = $process->getOutput();

        self::assertStringContainsString('access decision manager IS proxified.', $output);
        self::assertStringContainsString('access decision WORKS.', $output);

        // the one that speaks to the bug itself rather than to the plumbing around it: being pooled says
        // nothing with kernel.debug on, where what gets pooled is the decorator and the manager holding
        // the stack sits behind it
        self::assertStringContainsString('coroutines DO NOT SHARE the stack.', $output);
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
            // decorated by a TraceableAccessDecisionManager, since kernel.debug is on
            'debug_on' => [
                ['APP_ENV' => 'coroutines_security', 'APP_DEBUG' => '1', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1'],
            ],
            // plain manager, no decorator - kernel.debug is off, as in production
            'debug_off' => [
                ['APP_ENV' => 'coroutines_security', 'APP_DEBUG' => '0', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1'],
            ],
        ];
    }
}
