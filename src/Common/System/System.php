<?php

declare(strict_types=1);

namespace K911\Swoole\Common\System;

final class System
{
    private function __construct(
        private Extension $extension,
        private Version $version,
    ) {
    }

    public static function create(): self
    {
        return new self(Extension::create(), Version::create());
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
