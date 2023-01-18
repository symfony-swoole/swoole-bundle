<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container;

use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use ZEngine\Reflection\ReflectionClass;
use ZEngine\Reflection\ReflectionMethod;

final class ContainerModifier
{
    private static $alreadyOverridden = [];

    public static function includeOverriddenContainer(string $cacheDir, string $containerClass, bool $isDebug): void
    {
        $cache = new ConfigCache($cacheDir.'/'.$containerClass.'.php', $isDebug);
        $cachePath = $cache->getPath();

        if (!file_exists($cachePath)) {
            return;
        }

        $content = file_get_contents($cachePath);
        $found = preg_match('/(?P<class>\\\Container.*)::/', $content, $matches);

        if (!$found || !isset($matches['class'])) {
            throw new \UnexpectedValueException(sprintf('Container class missing in file %s', $cachePath));
        }

        $overriddenFqcn = $matches['class'].'_Overridden';
        $overriddenFile = $cacheDir.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $overriddenFqcn).'.php';

        if (!file_exists($overriddenFile)) {
            return;
        }

        require_once $overriddenFile;
    }

    public static function modifyContainer(BlockingContainer $container, string $cacheDir): void
    {
        $reflContainer = new ReflectionClass($container);
        BlockingContainer::setBuildContainerNs($reflContainer->getNamespaceName());

        if (isset(self::$alreadyOverridden[$reflContainer->getName()])) {
            return;
        }

        self::overrideGeneratedContainer($reflContainer, $cacheDir);
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
        if (!$reflContainer->hasMethod('createProxy')) {
            return;
        }

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

    private static function overrideGeneratedContainer(ReflectionClass $reflContainer, string $cacheDir): void
    {
        $fs = new Filesystem();
        $containerFqcn = $reflContainer->getName();
        $overriddenFqcn = $containerFqcn.'_Overridden';
        $classParts = explode('\\', $containerFqcn);
        $containerClass = array_pop($classParts);
        $overriddenClass = $containerClass.'_Overridden';
        $containerFile = $cacheDir.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $containerFqcn).'.php';
        $overriddenFile = $cacheDir.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $overriddenFqcn).'.php';

        if (file_exists($overriddenFile)) {
            return;
        }

        $containerSource = file_get_contents($containerFile);
        $overriddenSource = str_replace('class '.$containerClass, 'class '.$overriddenClass, $containerSource);

        // dump opcache.blacklist_filename
        $blacklistFile = $cacheDir.DIRECTORY_SEPARATOR.ContainerConstants::PARAM_CACHE_FOLDER.DIRECTORY_SEPARATOR.'opcache'.DIRECTORY_SEPARATOR.'blacklist.txt';
        $blacklistFiles = [$containerFile, $overriddenFile];
        $blacklistFileContent = implode(PHP_EOL, $blacklistFiles).PHP_EOL;
        $fs->dumpFile($blacklistFile, $blacklistFileContent);

        // methods override
        $ignoredMethods = self::getIgnoredGetters();
        $methods = $reflContainer->getMethods(\ReflectionMethod::IS_PROTECTED);
        $methodsCodes = [];

        foreach ($methods as $method) {
            $methodName = $method->getName();

            if (isset($ignoredMethods[$methodName]) || 0 !== strpos($methodName, 'get')) {
                continue;
            }

            $methodsCodes[] = self::generateOverriddenGetter($method);
        }

        $namespace = $reflContainer->getNamespaceName();
        $methodsCode = implode(PHP_EOL.PHP_EOL, $methodsCodes);
        $newContainerSource = <<<EOF
            <?php

            namespace $namespace;

            class $containerClass extends $overriddenClass
            {
            $methodsCode
            }
            EOF;

        $fs->copy($containerFile, $overriddenFile);
        $fs->dumpFile($overriddenFile, $overriddenSource);
        $fs->dumpFile($containerFile, $newContainerSource);
    }

    private static function generateOverriddenGetter(ReflectionMethod $method): string
    {
        $methodName = $method->getName();

        return $method->getNumberOfParameters() > 0 ?
            self::generateLazyGetter($methodName) : self::generateCasualGetter($methodName);
    }

    private static function generateLazyGetter(string $methodName): string
    {
        return <<<EOF
                    protected function $methodName(\$lazyLoad = true) {
                        // this might be a weird SF container bug or idk... but SF container keeps calling this factory method
                        // with service id
                        if (is_string(\$lazyLoad)) {
                            \$lazyLoad = true;
                        }

                        \$lock = self::\$locking->acquire('$methodName'.'_'.(\$lazyLoad ? 'lazy' : ''));

                        try {
                            \$return = parent::{$methodName}(\$lazyLoad);
                        } finally {
                            \$lock->release();
                        }

                        return \$return;
                    }
            EOF;
    }

    private static function generateCasualGetter(string $methodName): string
    {
        return <<<EOF
                    protected function $methodName() {
                        \$lock = self::\$locking->acquire('$methodName');

                        try {
                            \$return = parent::{$methodName}();
                        } finally {
                            \$lock->release();
                        }

                        return \$return;
                    }
            EOF;
    }
}
