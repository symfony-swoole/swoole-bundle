<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\Proxy;

use Symfony\Component\Filesystem\Filesystem;

final class ProxyDirectoryHandler
{
    private Filesystem $fileSystem;

    private string $proxyDir;

    private bool $proxyDirExists = false;

    public function __construct(Filesystem $fileSystem, string $proxyDir)
    {
        $this->fileSystem = $fileSystem;
        $this->proxyDir = $proxyDir;
    }

    public function ensureProxyDirExists(): void
    {
        if ($this->proxyDirExists) {
            return;
        }

        if ($this->fileSystem->exists($this->proxyDir)) {
            $this->proxyDirExists = true;

            return;
        }

        $this->fileSystem->mkdir($this->proxyDir);
        $this->proxyDirExists = true;
    }
}
