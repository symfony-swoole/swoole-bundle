<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * A readonly stateful service, pooled like any other: one instance per coroutine.
 *
 * The service is a readonly curl client - readonly, and still holding a handle two coroutines must not
 * share. It is also final, so this is the whole road a real one takes: z-engine removes `final`, and the
 * proxy the pool is reached through has to be readonly itself to extend it.
 */
final class ReadonlyServiceProxificationTest extends ServerTestCase
{
    public function testAReadonlyStatefulServiceIsProxifiedAndPooledPerCoroutine(): void
    {
        $output = $this->runReadonlyCurlClientProxyCheck();

        self::assertStringContainsString('Client IS proxified.', $output);
        self::assertStringContainsString('Client class is readonly.', $output);
        // a public readonly property of the service, read through the proxy's __get
        self::assertStringContainsString('Base URI: http://localhost', $output);

        $first = self::handlesOf('A', $output);
        $second = self::handlesOf('B', $output);

        self::assertSame($first[0], $first[1], 'Coroutine A was handed a different client between two calls.');
        self::assertSame($second[0], $second[1], 'Coroutine B was handed a different client between two calls.');
        self::assertNotSame(
            $first[0],
            $second[0],
            'Two coroutines using the client at the same time drove the same curl handle.',
        );
    }

    private function runReadonlyCurlClientProxyCheck(): string
    {
        $process = $this->createConsoleProcess(['test:readonly-curl-client:proxy-check'], ['APP_ENV' => 'coroutines']);
        $process->setTimeout(self::coverageEnabled() ? 30 : 15);
        $process->run();

        $this->assertProcessSucceeded($process);

        return $process->getOutput();
    }

    /**
     * @return array{int, int}
     */
    private static function handlesOf(string $coroutine, string $output): array
    {
        self::assertSame(
            1,
            preg_match(sprintf('/^Coroutine %s handles: (\d+) (\d+)$/m', $coroutine), $output, $matches),
            sprintf('No handles reported for coroutine %s. Output: %s', $coroutine, $output),
        );

        return [(int) $matches[1], (int) $matches[2]];
    }
}
