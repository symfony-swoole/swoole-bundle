<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Doctrine\ORM;

use Doctrine\Persistence\ObjectManager;
use K911\Swoole\Bridge\Symfony\Container\Resetter;

final class EntityManagerResetter implements Resetter
{
    public function reset(object $service): void
    {
        if (!$service instanceof ObjectManager) {
            throw new \UnexpectedValueException(\sprintf('Invalid service - expected %s, got %s', ObjectManager::class, $service::class));
        }

        $service->clear();
    }
}
