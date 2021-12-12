<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container;

use K911\Swoole\Component\Locking\FirstTimeOnlyLocking;
use Symfony\Component\DependencyInjection\Container;

class BlockingContainer extends Container
{
    /**
     * {@inheritDoc}
     */
    public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
    {
        static $locking;
        $locking = FirstTimeOnlyLocking::init($locking);
        $lock = $locking->acquire($id);

        try {
            $service = parent::get($id, $invalidBehavior);
        } finally {
            $lock->release();
        }

        return $service;
    }
}
