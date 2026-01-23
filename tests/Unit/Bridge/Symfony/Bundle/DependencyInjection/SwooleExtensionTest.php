<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\DependencyInjection;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\SwooleExtension;
use SwooleBundle\SwooleBundle\Server\HttpServerConfiguration;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SwooleExtensionTest extends TestCase
{
    private $extension;
    private $container;

    protected function setUp(): void
    {
        $this->extension = new SwooleExtension();
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.debug', false);
        $this->container->setParameter('kernel.environment', 'test');
    }

    public function testGrpcEnablesOpenHttp2Protocol(): void
    {
        $config = [
            'http_server' => [
                'grpc' => true,
            ],
        ];

        $this->extension->load([$config], $this->container);

        $httpServerConfigDefinition = $this->container->getDefinition(HttpServerConfiguration::class);
        $swooleSettings = $httpServerConfigDefinition->getArgument(3);

        $this->assertTrue($swooleSettings['open_http2_protocol']);
    }
}
