<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Runtime\Watch;

final readonly class FileChange
{
    /**
     * @param array<string> $lintTargets added/modified .php files to `php -l` before restart
     */
    public function __construct(
        public bool $hasChanges,
        public string $reason,
        public array $lintTargets,
    ) {}

    public static function none(): self
    {
        return new self(false, '', []);
    }
}
