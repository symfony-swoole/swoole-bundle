<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleDebugServicePoolsCommandTest extends ServerTestCase
{
    public function testListsWhatEachProxifierPooled(): void
    {
        $output = $this->runServicePoolsCommand(['--env=coroutines']);

        // Symfony's own services, pooled because they carry per-request state: the request stack, the
        // context the router builds urls against, and twig with the two form services beside it.
        self::assertStringContainsString(' * request_stack' . PHP_EOL, $output);
        self::assertStringContainsString(' * router.request_context' . PHP_EOL, $output);
        self::assertStringContainsString(' * twig' . PHP_EOL, $output);

        // The transport factory MessengerProcessor tags: the transport itself cannot be pooled, so the
        // factory is wrapped and its method hands back a pool proxy instead.
        self::assertStringContainsString(' * messenger.transport.doctrine.factory' . PHP_EOL, $output);

        self::assertGreaterThan(1, $this->sectionCount('Container instantiation', $output));
        self::assertGreaterThan(0, $this->sectionCount('Unmanaged factory instantiation', $output));
    }

    /**
     * The suffix a container-instantiated service is renamed with is a prefix of the unmanaged factory
     * one, so a factory reported in both sections would mean the ids are being matched loosely.
     */
    public function testAnUnmanagedFactoryIsNotAlsoCountedAsContainerInstantiated(): void
    {
        $output = $this->runServicePoolsCommand(['--env=coroutines', '--filter=messenger.transport.doctrine.factory']);

        self::assertSame(0, $this->sectionCount('Container instantiation', $output));
        self::assertSame(1, $this->sectionCount('Unmanaged factory instantiation', $output));
    }

    public function testFilterNarrowsTheListing(): void
    {
        $output = $this->runServicePoolsCommand(['--env=coroutines', '--filter=twig']);

        self::assertStringContainsString(' * twig.form.renderer' . PHP_EOL, $output);
        self::assertStringNotContainsString(' * request_stack' . PHP_EOL, $output);
    }

    /**
     * The `test` environment leaves coroutine support off, and nothing is proxified without it - which
     * has to read as "this does not apply here" rather than as an empty finding.
     */
    public function testReportsThatCoroutineSupportIsDisabled(): void
    {
        $output = $this->runServicePoolsCommand([]);

        self::assertStringContainsString('Coroutine support is disabled', $output);
        self::assertStringNotContainsString('Container instantiation', $output);
    }

    /**
     * @param array<int, string> $args
     */
    private function runServicePoolsCommand(array $args): string
    {
        $command = $this->createConsoleProcess(array_merge(['swoole:debug:service-pools', '--no-ansi'], $args));

        $command->setTimeout(self::coverageEnabled() ? 30 : 15);
        $command->run();

        $this->assertProcessSucceeded($command);

        return $command->getOutput();
    }

    private function sectionCount(string $section, string $output): int
    {
        self::assertSame(
            1,
            preg_match(sprintf('/%s \((\d+)\)/', preg_quote($section, '/')), $output, $matches),
            sprintf('Section "%s" is missing from the output.', $section),
        );

        return (int) $matches[1];
    }
}
