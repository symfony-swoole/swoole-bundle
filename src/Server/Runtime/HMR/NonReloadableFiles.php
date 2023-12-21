<?php

declare(strict_types=1);

namespace K911\Swoole\Server\Runtime\HMR;

use K911\Swoole\Server\Runtime\BootableInterface;
use Symfony\Component\Filesystem\Filesystem;

final class NonReloadableFiles implements BootableInterface
{
    public function __construct(
        private readonly string $kernelCacheDir,
        private readonly string $filePathDir,
        private readonly Filesystem $fileSystem,
    ) {
    }

    public function boot(array $runtimeConfiguration = []): void
    {
        // Files included before server start cannot be reloaded due to PHP limitations
        $allFiles = \get_included_files();
        // list of files when full/hard server reload is needed
        $this->fileSystem->dumpFile($this->filePathDir.'/nonReloadableFiles.txt', \implode("\n", $allFiles));
        // exclude vendor and cache files (assume they are still valid) to get only app files and perform php lint before hard server reload
        $appFiles = \array_filter($allFiles, fn ($values) => !(\str_contains($values, '/vendor/') || \str_starts_with($values, $this->kernelCacheDir)));
        $this->fileSystem->dumpFile($this->filePathDir.'/nonReloadableAppFiles.txt', \implode("\n", $appFiles));
    }
}
