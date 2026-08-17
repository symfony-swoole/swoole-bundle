<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * The access decision stack one layer up from the manager, in the class templates actually call.
 *
 * AuthorizationChecker keeps the decision being made on $accessDecisionStack and the user being asked
 * about on $tokenStack, pushed on the way into isGranted() and popped in a finally - the same shape
 * SecurityProcessor fixes in AccessDecisionManager, in the class that calls into it. Shared, a voter
 * doing any I/O suspends its coroutine mid-decision and leaves the stack non-empty for whoever runs
 * next, so `end()` hands that request somebody else's decision:
 *
 *   FiberViber\ConcurrencyException: Cross-coroutine access detected: [property_fetch_w]
 *   Symfony\Component\Security\Core\Authorization\AuthorizationChecker::$accessDecisionStack is owned
 *   by coroutine #2 but accessed by coroutine #4
 *
 * Under load in a profiled dev worker this is the most frequent race of the lot, and it arrives from
 * base.html.twig by way of SecurityExtension::isGranted() - every `is_granted()` in a template is a
 * push onto that one shared stack.
 *
 * Both stacks balance themselves, so no resetter is involved: an instance per coroutine is the fix.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServicesPass
 */
final class AuthorizationCheckerCoroutineSafetyTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // What is asserted here is a property of the compiled container, and a container compiled before
        // this was fixed answers just as happily from the cache.
        $this->deleteVarDirectory();
    }

    /**
     * @param array{APP_ENV: string, APP_DEBUG: string, WORKER_COUNT: string, REACTOR_COUNT: string} $envs
     */
    #[DataProvider('debugToggleDataProvider')]
    public function testTheAuthorizationCheckerIsProxifiedAndStillAnswers(array $envs): void
    {
        $process = $this->createConsoleProcess(['test:authorization-checker:proxy-check'], $envs);
        $process->setTimeout(self::coverageEnabled() ? 30 : 15);
        $process->run();

        $this->assertProcessSucceeded($process);

        $output = $process->getOutput();

        self::assertStringContainsString('authorization checker IS proxified.', $output);
        // isGranted() is final on the class, so the proxy only exists at all because the bundle strips
        // final flags before generating it - a checker that answers proves that went through
        self::assertStringContainsString('authorization check WORKS.', $output);
        self::assertStringContainsString('coroutines DO NOT SHARE the stacks.', $output);
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
            'debug_on' => [
                ['APP_ENV' => 'coroutines_security', 'APP_DEBUG' => '1', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1'],
            ],
            'debug_off' => [
                ['APP_ENV' => 'coroutines_security', 'APP_DEBUG' => '0', 'WORKER_COUNT' => '1', 'REACTOR_COUNT' => '1'],
            ],
        ];
    }
}
