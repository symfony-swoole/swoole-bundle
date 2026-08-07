<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Router;

use Override;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;

final class LockingConfigCacheFactory implements ConfigCacheFactoryInterface
{
    /**
     * @var array<string, ConfigCacheInterface>
     */
    private static array $initializationCompleted = [];

    public function __construct(
        private ConfigCacheFactoryInterface $decorated,
        private Mutex $mutex,
    ) {}

    #[Override]
    public function cache(string $file, callable $callable): ConfigCacheInterface
    {
        if (isset(self::$initializationCompleted[$file])) {
            return $this->decorated->cache($file, $callable);
        }

        try {
            $this->mutex->acquire();

            if (isset(self::$initializationCompleted[$file])) {
                return $this->decorated->cache($file, $callable);
            }

            $cache = $this->decorated->cache($file, $callable);
            self::$initializationCompleted[$file] = $cache;

            return $cache;
        } finally {
            $this->mutex->release();
        }
    }
}
