<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SymfonyCacheWarmingWithCoroutinesTest extends ServerTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    /**
     * @param array{APP_ENV: string, APP_DEBUG: string} $envs
     * @param array<string> $commandArgs
     */
    #[DataProvider('debugModeDataProvider')]
    public function testCacheClearTwiceAndWarmupSucceed(array $envs, array $commandArgs): void
    {
        $this->runConsoleCommandSuccessfully(array_merge(['cache:clear'], $commandArgs), $envs);
        $this->runConsoleCommandSuccessfully(array_merge(['cache:clear'], $commandArgs), $envs);
        $this->runConsoleCommandSuccessfully(array_merge(['cache:warmup'], $commandArgs), $envs);
    }

    /**
     * @return array<string, array{envs: array{APP_ENV: string, APP_DEBUG: string}, commandArgs: array<string>}>
     */
    public static function debugModeDataProvider(): array
    {
        return [
            'debug off' => [
                'envs' => ['APP_ENV' => 'coroutines', 'APP_DEBUG' => '0'],
                'commandArgs' => ['--no-debug'],
            ],
            'debug on' => [
                'envs' => ['APP_ENV' => 'coroutines', 'APP_DEBUG' => '1'],
                'commandArgs' => [],
            ],
        ];
    }

    /**
     * @param array<string> $args
     * @param array<string, string> $envs
     */
    private function runConsoleCommandSuccessfully(array $args, array $envs): void
    {
        $process = $this->createConsoleProcess($args, $envs);
        $process->setTimeout(5);
        $process->run();

        $this->assertProcessSucceeded($process);
        self::assertTrue($process->isSuccessful());

        $output = $process->getOutput() . $process->getErrorOutput();

        self::assertStringNotContainsString('Undefined property', $output);
        self::assertStringNotContainsString('dynamic property', $output);
    }
}
