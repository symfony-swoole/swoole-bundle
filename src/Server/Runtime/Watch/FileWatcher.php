<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Runtime\Watch;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class FileWatcher
{
    private const array CONFIG_EXTENSIONS = ['.yaml', '.yml', '.xml', '.env', '.neon', '.ini'];

    /**
     * @param array<string> $paths absolute directory roots to watch
     */
    public function __construct(private array $paths) {}

    /**
     * @return array<string, int> absolute watched-file path => mtime
     */
    public function snapshot(): array
    {
        clearstatcache();

        $map = [];
        foreach ($this->paths as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile()) {
                    continue;
                }

                $path = $file->getPathname();
                if (!self::isWatched($path)) {
                    continue;
                }

                $mtime = @filemtime($path);
                if ($mtime === false) {
                    continue;
                }

                $map[$path] = $mtime;
            }
        }

        return $map;
    }

    /**
     * @param array<string, int> $old
     * @param array<string, int> $new
     */
    public function classify(array $old, array $new): FileChange
    {
        $added = array_keys(array_diff_key($new, $old));
        $removed = array_keys(array_diff_key($old, $new));

        $modified = [];
        foreach ($new as $path => $mtime) {
            if (!isset($old[$path]) || $old[$path] === $mtime) {
                continue;
            }

            $modified[] = $path;
        }

        if ($added === [] && $removed === [] && $modified === []) {
            return FileChange::none();
        }

        $lintTargets = array_values(array_filter(
            [...$added, ...$modified],
            static fn(string $path): bool => str_ends_with($path, '.php'),
        ));

        $reasonParts = [];
        if ($added !== []) {
            $reasonParts[] = 'added ' . implode(', ', $added);
        }
        if ($removed !== []) {
            $reasonParts[] = 'removed ' . implode(', ', $removed);
        }
        if ($modified !== []) {
            $reasonParts[] = 'modified ' . implode(', ', $modified);
        }

        return new FileChange(true, implode('; ', $reasonParts), $lintTargets);
    }

    private static function isWatched(string $path): bool
    {
        return str_ends_with($path, '.php') || self::isConfig($path);
    }

    private static function isConfig(string $path): bool
    {
        if (str_contains($path, '/config/')) {
            return true;
        }

        foreach (self::CONFIG_EXTENSIONS as $extension) {
            if (str_ends_with($path, $extension)) {
                return true;
            }
        }

        return false;
    }
}
