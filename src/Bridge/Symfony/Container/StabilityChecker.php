<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container;

interface StabilityChecker
{
    public function isStable(object $service): bool;

    /**
     * @return class-string
     */
    public static function getSupportedClass(): string;
}
