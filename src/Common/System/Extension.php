<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Common\System;

final class Extension
{
    public const OPENSWOOLE = 'openswoole';
    public const SWOOLE = 'swoole';

    private function __construct(
        private readonly string $extension,
    ) {
    }

    public static function create(): self
    {
        if (\extension_loaded(Extension::OPENSWOOLE)) {
            return Extension::openswoole();
        } elseif (\extension_loaded(Extension::SWOOLE)) {
            return Extension::swoole();
        }

        throw new \RuntimeException('Unable to find Swoole extension.');
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
        return self::SWOOLE === $this->extension;
    }

    public function isOpenSwoole(): bool
    {
        return self::OPENSWOOLE === $this->extension;
    }

    public function toString(): string
    {
        return $this->extension;
    }
}
