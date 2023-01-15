<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container;

use Symfony\Component\DependencyInjection\Container;
use ZEngine\Reflection\ReflectionClass;
use ZEngine\Reflection\ReflectionMethod;

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

        self::overrideGetters($container, $reflContainer);
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
        $refl->addMethod('doOverridden', $reflDo->getClosure());

        $reflDo->redefine(function ($container, $lazyLoad = true) {
            $lockName = get_called_class().'::DO_'.($lazyLoad ? 'lazy' : '');
            $lock = self::$locking->acquire($lockName);

            try {
                $return = self::doOverridden($container, $lazyLoad);
            } finally {
                $lock->release();
            }

            return $return;
        });
        self::$alreadyOverridden[$fileToLoad] = true;
    }

    public static function getOverriddenGetterName(string $methodName): string
    {
        return $methodName.'_Overridden';
    }

    public static function getIgnoredGetters(): array
    {
        $reflBlockingContainer = new ReflectionClass(BlockingContainer::class);
        $methods = $reflBlockingContainer->getMethods(\ReflectionMethod::IS_PROTECTED);
        $methodNames = array_map(fn (ReflectionMethod $method): string => $method->getName(), $methods);
        $methodNames = array_merge($methodNames, get_class_methods(BlockingContainer::class));
        $getters = array_filter($methodNames, fn (string $methodName): bool => 0 === strpos($methodName, 'get'));
        $getters[] = 'getDefaultParameters';

        return array_flip($getters);
    }

    private static function overrideCreateProxy(BlockingContainer $container, ReflectionClass $reflContainer): void
    {
        $createProxyRefl = $reflContainer->getMethod('createProxy');
        $reflContainer->addMethod('createProxyOverridden', $createProxyRefl->getClosure($container));
        $createProxyRefl->redefine(function ($class, \Closure $factory) {
            $lock = self::$locking->acquire($class);

            try {
                $return = $this->createProxyOverridden($class, $factory);
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
        $reflContainer->addMethod('loadOverridden', $loadRefl->getClosure($container));
        $loadRefl->redefine(function (string $file) {
            $lock = self::$locking->acquire($file);

            try {
                $return = $this->loadOverridden($file);
            } finally {
                $lock->release();
            }

            return $return;
        });
    }

    private static function overrideGeneratedLoad(BlockingContainer $container, ReflectionClass $reflContainer): void
    {
        $loadRefl = $reflContainer->getMethod('load');
        $reflContainer->addMethod('loadOverridden', $loadRefl->getClosure($container));
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

                $return = $this->loadOverridden($file, $lazyLoad);
            } finally {
                $lock->release();
            }

            return $return;
        });
    }

    private static function overrideGetters(BlockingContainer $container, ReflectionClass $reflContainer): void
    {
        $ignoredMethods = self::getIgnoredGetters();
        $methods = $reflContainer->getMethods(\ReflectionMethod::IS_PROTECTED);

        foreach ($methods as $method) {
            $methodName = $method->getName();

            if (isset($ignoredMethods[$methodName]) || 0 !== strpos($methodName, 'get')) {
                continue;
            }

            self::overrideGetter($container, $reflContainer, $method);
        }
    }

    private static function overrideGetter(BlockingContainer $container, ReflectionClass $reflContainer, ReflectionMethod $method): void
    {
        $methodName = $method->getName();
        $overriddenMethodName = self::getOverriddenGetterName($methodName);
        $reflContainer->addMethod($overriddenMethodName, $method->getClosure($container));
        $newGetter = $method->getNumberOfParameters() > 0 ? self::createLazyGetter() : self::createCasualGetter();
        $method->redefine($newGetter);
    }

    private static function createLazyGetter(): \Closure
    {
        return function ($lazyLoad = true) {
            // this might be a weird SF container bug or idk... but SF container keeps calling this factory method
            // with service id
            if (is_string($lazyLoad)) {
                $lazyLoad = true;
            }

            $overriddenMethodName = ContainerModifier::getOverriddenGetterName(
                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['function']
            );
            $lock = self::$locking->acquire($overriddenMethodName.'_'.($lazyLoad ? 'lazy' : ''));

            try {
                $return = $this->{$overriddenMethodName}($lazyLoad);
            } finally {
                $lock->release();
            }

            return $return;
        };
    }

    private static function createCasualGetter(): \Closure
    {
        return function () {
            $overriddenMethodName = ContainerModifier::getOverriddenGetterName(
                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['function']
            );
            $lock = self::$locking->acquire($overriddenMethodName);

            try {
                $return = $this->{$overriddenMethodName}();
            } finally {
                $lock->release();
            }

            return $return;
        };
    }
}
