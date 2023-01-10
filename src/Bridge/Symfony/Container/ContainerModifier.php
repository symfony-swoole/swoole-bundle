<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container;

use Symfony\Component\DependencyInjection\Container;
use ZEngine\Reflection\ReflectionClass;

final class ContainerModifier
{
    private static $alreadyOverridden = [];

    public static function modifyContainer(BlockingContainer $container): void
    {
        $reflContainer = new ReflectionClass($container);
        BlockingContainer::setBuildContainerNs($reflContainer->getNamespaceName());

        if (isset(self::$alreadyOverridden[$reflContainer->getName()])) {
            return;
        }

        if (!$reflContainer->hasMethod('createProxy')) {
            return;
        }

        self::overrideCreateProxy($container, $reflContainer);
        self::overrideLoad($container, $reflContainer);
        self::$alreadyOverridden[$reflContainer->getName()] = true;
    }

    public static function overrideDoInExtension(string $containerDir, string $fileToLoad, string $class): void
    {
        if (isset(self::$alreadyOverridden[$fileToLoad])) {
            return;
        }

        require $containerDir.\DIRECTORY_SEPARATOR.$fileToLoad;

        $refl = new ReflectionClass($class);
        $reflDo = $refl->getMethod('do');
        $refl->addMethod('doOriginal', $reflDo->getClosure());

        $reflDo->redefine(function ($container, $lazyLoad = true) {
            $lockName = get_called_class().'::DO';
            $lock = self::$locking->acquire($lockName);

            try {
                $return = self::doOriginal($container, $lazyLoad);
            } finally {
                $lock->release();
            }

            return $return;
        });
        self::$alreadyOverridden[$fileToLoad] = true;
    }

    private static function overrideCreateProxy(BlockingContainer $container, ReflectionClass $reflContainer): void
    {
        $createProxyRefl = $reflContainer->getMethod('createProxy');
        $reflContainer->addMethod('createProxyOriginal', $createProxyRefl->getClosure($container));
        $createProxyRefl->redefine(function ($class, \Closure $factory) {
            $lock = self::$locking->acquire($class);

            try {
                $return = $this->createProxyOriginal($class, $factory);
            } finally {
                $lock->release();
            }

            return $return;
        });
    }

    private static function overrideLoad(BlockingContainer $container, ReflectionClass $reflContainer): void
    {
        $loadRefl = $reflContainer->getMethod('load');

        if (Container::class == $loadRefl->getDeclaringClass()->getName()) {
            self::overrideOriginalContainerLoad($container, $reflContainer);

            return;
        }

        self::overrideGeneratedLoad($container, $reflContainer);
    }

    private static function overrideOriginalContainerLoad(BlockingContainer $container, ReflectionClass $reflContainer): void
    {
        $loadRefl = $reflContainer->getMethod('load');
        $reflContainer->addMethod('loadOriginal', $loadRefl->getClosure($container));
        $loadRefl->redefine(function (string $file) {
            $lock = self::$locking->acquire($file);

            try {
                $return = $this->loadOriginal($file);
            } finally {
                $lock->release();
            }

            return $return;
        });
    }

    private static function overrideGeneratedLoad(BlockingContainer $container, ReflectionClass $reflContainer): void
    {
        $loadRefl = $reflContainer->getMethod('load');
        $reflContainer->addMethod('loadOriginal', $loadRefl->getClosure($container));
        $loadRefl->redefine(function ($file, $lazyLoad = true) {
            $lock = self::$locking->acquire($file);

            try {
                $fileToLoad = $file;
                $class = self::$buildContainerNs.'\\'.$file;
                if ('.' === $file[-4]) {
                    $class = substr($class, 0, -4);
                } else {
                    $fileToLoad .= '.php';
                }

                if (!class_exists($class, false)) {
                    ContainerModifier::overrideDoInExtension($this->containerDir, $fileToLoad, $class);
                }

                $return = $this->loadOriginal($file, $lazyLoad);
            } finally {
                $lock->release();
            }

            return $return;
        });
    }
}
