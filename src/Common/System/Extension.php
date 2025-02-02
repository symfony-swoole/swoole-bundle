<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Common\System;

use RuntimeException;

final readonly class Extension
{
    public const OPENSWOOLE = 'openswoole';
    public const SWOOLE = 'swoole';

    private function __construct(
        private string $extension,
    ) {}

    public static function create(): self
    {
        if (extension_loaded(self::OPENSWOOLE)) {
            return self::openswoole();
        }

        if (extension_loaded(self::SWOOLE)) {
            return self::swoole();
        }

        throw new RuntimeException('Unable to find Swoole extension.');
    }

    public static function openswoole(): self
    {
        return new self(self::OPENSWOOLE);
    }

    public static function swoole(): self
    {
        return new self(self::SWOOLE);
    }

    public function isSwoole(): bool
    {
        return $this->extension === self::SWOOLE;
    }

    public function isOpenSwoole(): bool
    {
        return $this->extension === self::OPENSWOOLE;
    }

    public function toString(): string
    {
        return $this->extension;
    }
}
