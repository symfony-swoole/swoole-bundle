<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Runtime\HMR;

use Swoole\Server;

final class ReloadCountingServerMock extends Server
{
    private int $reloadCount = 0;
    private static ?self $instance = null;

    private function __construct()
    {
        parent::__construct('localhost', 31998);
    }

    public static function make(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function reload(bool $onlyReloadTaskworker = false): bool
    {
        ++$this->reloadCount;

        return true;
    }

    public function reloadCount(): int
    {
        return $this->reloadCount;
    }
}
