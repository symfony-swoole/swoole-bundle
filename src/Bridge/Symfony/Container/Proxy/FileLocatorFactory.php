<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy;

use ProxyManager\FileLocator\FileLocator;
use ProxyManager\FileLocator\FileLocatorInterface;

final readonly class FileLocatorFactory
{
    public function __construct(private ProxyDirectoryHandler $directoryHandler) {}

    public function createFileLocator(string $proxiesDirectory): FileLocatorInterface
    {
        $this->directoryHandler->ensureProxyDirExists();

        return new FileLocator($proxiesDirectory);
    }
}
