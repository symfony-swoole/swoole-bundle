<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * The stateful firewall's context and its context listener, both of which a worker keeps alive far longer
 * than they were written for.
 *
 * With the profiler on, TraceableFirewallListener replaces a LazyFirewallContext's listeners with timing
 * wrappers and writes them back onto the context. The ContextListener notes on itself that it registered a
 * response listener on the firewall's dispatcher, and does not always manage to clear that note. Neither is
 * a problem for a container thrown away after one request; shared by a worker, one is rewritten by every
 * request that passes through it and the other stops registering entirely, which quietly ends session
 * persistence.
 *
 * The coroutines_security environment configures the firewall this needs (see its security.php): stateful
 * and lazy, which is what makes SecurityBundle build both services in the first place.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Security\SecurityProcessor
 */
final class SecurityFirewallContextWorkerSafetyTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    /**
     * @param array<string, string> $envs
     */
    #[DataProvider('securityEnvironmentDataProvider')]
    public function testTheFirewallContextIsHandedOutFreshAndItsListenerIsPooled(array $envs): void
    {
        $process = $this->createConsoleProcess(['test:security-firewall-context:proxy-check'], $envs);
        $process->setTimeout(self::coverageEnabled() ? 60 : 30);
        $process->run();

        $this->assertProcessSucceeded($process);

        $output = $process->getOutput();

        // the profiler rewrites the listeners of whichever context it is given, so no two requests may be
        // given the same one
        self::assertStringContainsString('requests DO NOT SHARE the firewall context.', $output);

        // and the listener carrying the registered flag needs one instance per coroutine
        self::assertStringContainsString('context listener IS proxified.', $output);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function securityEnvironmentDataProvider(): iterable
    {
        // only with the profiler on is anything rewriting the context, which is what the fix is guarded by
        yield 'debug on' => [
            ['APP_ENV' => 'coroutines_security', 'APP_DEBUG' => '1', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1'],
        ];
    }
}
