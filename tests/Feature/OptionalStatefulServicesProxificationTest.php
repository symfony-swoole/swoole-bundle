<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class OptionalStatefulServicesProxificationTest extends ServerTestCase
{
    public function testOptionalServiceIsProxifiedWhenItsDefinitionClassIsInstantiable(): void
    {
        $output = $this->runSluggerProxyCheck('coroutines');

        self::assertStringContainsString('Slugger IS proxified.', $output);
        self::assertStringContainsString('Slug: Hello-World', $output);
    }

    public function testOptionalServiceProxificationIsSkippedWhenItsDefinitionClassIsAnInterface(): void
    {
        $output = $this->runSluggerProxyCheck('coroutines_optional_services');

        self::assertStringContainsString('Slugger IS NOT proxified.', $output);
        self::assertStringContainsString('Slug: Hello-World', $output);
    }

    private function runSluggerProxyCheck(string $env): string
    {
        $process = $this->createConsoleProcess(['test:slugger:proxy-check'], ['APP_ENV' => $env]);
        $process->setTimeout(self::coverageEnabled() ? 30 : 15);
        $process->run();

        $this->assertProcessSucceeded($process);

        return $process->getOutput();
    }
}
