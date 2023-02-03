<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Doctrine\ORM;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use K911\Swoole\Bridge\Symfony\Container\StabilityChecker;

final class EntityManagerStabilityChecker implements StabilityChecker
{
    public function isStable(object $service): bool
    {
        if (!$service instanceof EntityManagerInterface) {
            throw new \UnexpectedValueException(\sprintf('Invalid service - expected %s, got %s', EntityManagerInterface::class, \get_class($service)));
        }

        return $service->isOpen();
    }

    public static function getSupportedClass(): string
    {
        return EntityManager::class;
    }
}
