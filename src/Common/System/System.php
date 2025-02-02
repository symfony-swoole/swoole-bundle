<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Common\System;

use OpenSwoole\Util;

final readonly class System
{
    private function __construct(
        private Extension $extension,
        private Version $version,
    ) {}

    public static function create(): self
    {
        $extension = Extension::create();
        $version = Version::fromVersionString($extension->isSwoole() ? swoole_version() : Util::getVersion());

        return new self($extension, $version);
    }

    public function extension(): Extension
    {
        return $this->extension;
    }

    public function version(): Version
    {
        return $this->version;
    }
}
