<?php

declare(strict_types=1);

namespace K911\Swoole\Common\System;

final class Version
{
    private function __construct(
        private string $versionString,
    ) {
    }

    public static function fromVersionString(string $versionString): self
    {
        if (!\preg_match("/\d+\.\d+\.\d+/i", $versionString, $matches)) {
            throw new \UnexpectedValueException(\sprintf('Cannot parse version from string "%s".', $versionString));
        }

        return new self($versionString);
    }

    public function toString(): string
    {
        return $this->versionString;
    }
}
