<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Modifier\Builder;

use ZEngine\Reflection\ReflectionClass;

interface Builder
{
    public function overrideGeneratedContainer(ReflectionClass $reflContainer, string $cacheDir, bool $isDebug): void;

    public function overrideGeneratedContainerGetters(ReflectionClass $reflContainer, string $cacheDir): void;
}
