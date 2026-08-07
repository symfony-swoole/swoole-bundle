<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\Configuration;
use SwooleBundle\SwooleBundle\Server\Configurator\WithHealthProcess;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class HealthConfigurationTest extends TestCase
{
    public function testHealthPathDefaultsToHealthz(): void
    {
        $config = $this->process(['healthcheck' => ['enabled' => true]]);

        self::assertSame(WithHealthProcess::DEFAULT_PATH, $config['http_server']['healthcheck']['path']);
    }

    public function testHealthPathIsConfigurable(): void
    {
        $config = $this->process(['healthcheck' => ['enabled' => true, 'path' => '/alive']]);

        self::assertSame('/alive', $config['http_server']['healthcheck']['path']);
    }

    public function testHealthPathWithoutLeadingSlashIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must start with a slash');

        $this->process(['healthcheck' => ['enabled' => true, 'path' => 'alive']]);
    }

    public function testCheckSchedulingHasDefaults(): void
    {
        $config = $this->process(['healthcheck' => ['enabled' => true]]);

        self::assertSame(
            ['interval' => 5, 'staleness_threshold' => 15],
            $config['http_server']['healthcheck']['checks'],
        );
    }

    public function testCheckSchedulingIsConfigurable(): void
    {
        $config = $this->process([
            'healthcheck' => ['enabled' => true, 'checks' => ['interval' => 1, 'staleness_threshold' => 3]],
        ]);

        self::assertSame(
            ['interval' => 1, 'staleness_threshold' => 3],
            $config['http_server']['healthcheck']['checks'],
        );
    }

    /**
     * A threshold below the interval leaves the endpoint permanently stale.
     */
    public function testStalenessThresholdBelowTheIntervalIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('staleness_threshold');

        $this->process([
            'healthcheck' => ['enabled' => true, 'checks' => ['interval' => 10, 'staleness_threshold' => 5]],
        ]);
    }

    /**
     * @param array<string, mixed> $httpServer
     * @return array<string, mixed>
     */
    private function process(array $httpServer): array
    {
        return (new Processor())->processConfiguration(
            Configuration::fromTreeBuilder(),
            [['http_server' => $httpServer]],
        );
    }
}
