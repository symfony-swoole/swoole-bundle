<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Doctrine\ORM;

use Doctrine\Persistence\ObjectManager;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Resetter;
use UnexpectedValueException;

final class EntityManagerResetter implements Resetter
{
    public function reset(object $service): void
    {
        if (!$service instanceof ObjectManager) {
            throw new UnexpectedValueException(
                sprintf('Invalid service - expected %s, got %s', ObjectManager::class, $service::class)
            );
        }

        $service->clear();
    }
}
