<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Doctrine\ORM;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\StabilityChecker;

final class EntityManagerStabilityChecker implements StabilityChecker
{
    public function isStable(object $service): bool
    {
        if (!$service instanceof EntityManagerInterface) {
            throw new \UnexpectedValueException(\sprintf('Invalid service - expected %s, got %s', EntityManagerInterface::class, $service::class));
        }

        return $service->isOpen();
    }

    public static function getSupportedClass(): string
    {
        return EntityManager::class;
    }
}
