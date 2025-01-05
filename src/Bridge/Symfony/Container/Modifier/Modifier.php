<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Modifier;

use Assert\Assertion;
use Composer\InstalledVersions;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\BlockingContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Modifier\Builder\Symfony54PlusBuilder;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Modifier\Builder\Symfony63PlusBuilder;
use ZEngine\Reflection\ReflectionClass;

final class Modifier
{
    /**
     * @var array<string, true>
     */
    private static array $alreadyOverridden = [];

    public static function modifyContainer(BlockingContainer $container, string $cacheDir, bool $isDebug): void
    {
        $reflContainer = new ReflectionClass($container);
        BlockingContainer::setBuildContainerNs($reflContainer->getNamespaceName());

        if (isset(self::$alreadyOverridden[$reflContainer->getName()])) {
            return;
        }

        $realVersion = InstalledVersions::getVersion('symfony/dependency-injection');
        Assertion::string($realVersion);
        $builder = version_compare('6.3', $realVersion) <= 0 ? new Symfony63PlusBuilder() : new Symfony54PlusBuilder();
        $builder->overrideGeneratedContainer($reflContainer, $cacheDir, $isDebug);
        $builder->overrideGeneratedContainerGetters($reflContainer, $cacheDir);
        self::$alreadyOverridden[$reflContainer->getName()] = true;
    }
}
