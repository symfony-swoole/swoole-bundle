<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Runtime\HMR;

use Assert\Assertion;
use Assert\AssertionFailedException;
use Closure;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\Runtime\Bootable;

final class StatHMR implements HotModuleReloader, Bootable
{
    /**
     * @var array<string, true> file path => true; files loaded before server start (never watched)
     */
    private array $nonReloadableFiles = [];

    /**
     * @var array<string, int> file path => last observed filemtime()
     */
    private array $watchedFiles = [];

    /**
     * @var Closure(): array<string>
     */
    private readonly Closure $includedFilesProvider;

    /**
     * @param array<string> $nonReloadableFiles
     * @param (Closure(): array<string>)|null $includedFilesProvider test seam; defaults to get_included_files()
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly string $kernelCacheDir,
        array $nonReloadableFiles = [],
        ?Closure $includedFilesProvider = null,
    ) {
        $this->includedFilesProvider = $includedFilesProvider ?? static fn(): array => get_included_files();
        $this->setNonReloadableFiles($nonReloadableFiles);
    }

    /**
     * {@inheritDoc}
     *
     * @throws AssertionFailedException
     */
    public function boot(array $runtimeConfiguration = []): void
    {
        if (!empty($runtimeConfiguration['nonReloadableFiles'])) {
            $this->setNonReloadableFiles($runtimeConfiguration['nonReloadableFiles']);
        }

        // Files included before server start cannot be reloaded due to PHP limitations.
        $this->setNonReloadableFiles(get_included_files());
    }

    public function tick(Server $server): void
    {
        clearstatcache();

        if ($this->detectChanges()) {
            $server->reload();
        }

        $this->watchFiles(($this->includedFilesProvider)());
    }

    private function detectChanges(): bool
    {
        $changed = false;

        foreach ($this->watchedFiles as $file => $mtime) {
            $current = @filemtime($file);

            if ($current === $mtime) {
                continue;
            }

            $changed = true;

            if ($current === false) {
                unset($this->watchedFiles[$file]);

                continue;
            }

            $this->watchedFiles[$file] = $current;
        }

        return $changed;
    }

    /**
     * @param array<string> $files
     */
    private function watchFiles(array $files): void
    {
        foreach ($files as $file) {
            if (isset($this->nonReloadableFiles[$file]) || isset($this->watchedFiles[$file])) {
                continue;
            }

            if (!$this->isReloadable($file)) {
                continue;
            }

            $mtime = @filemtime($file);

            if ($mtime === false) {
                continue;
            }

            $this->watchedFiles[$file] = $mtime;
        }
    }

    private function isReloadable(string $file): bool
    {
        return !str_starts_with($file, $this->kernelCacheDir);
    }

    /**
     * @param array<string> $nonReloadableFiles
     * @throws AssertionFailedException
     */
    private function setNonReloadableFiles(array $nonReloadableFiles): void
    {
        foreach ($nonReloadableFiles as $nonReloadableFile) {
            Assertion::file($nonReloadableFile);
            $this->nonReloadableFiles[$nonReloadableFile] = true;
        }
    }
}
