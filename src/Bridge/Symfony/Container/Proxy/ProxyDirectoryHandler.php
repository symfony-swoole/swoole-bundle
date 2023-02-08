<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\Proxy;

use Symfony\Component\Filesystem\Filesystem;

final class ProxyDirectoryHandler
{
    private bool $proxyDirExists = false;

    public function __construct(
        private Filesystem $fileSystem,
        private string $proxyDir
    ) {
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
