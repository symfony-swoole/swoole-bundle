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

    public static function modifyContainer(BlockingContainer $container, string $cacheDir, bool $isDebug): void
    {
        $reflContainer = new ReflectionClass($container);
        BlockingContainer::setBuildContainerNs($reflContainer->getNamespaceName());

        if (isset(self::$alreadyOverridden[$reflContainer->getName()])) {
            return;
        }

        self::overrideGeneratedContainer($reflContainer, $cacheDir, $isDebug);
        self::overrideGeneratedContainerGetters($reflContainer, $cacheDir);
        self::$alreadyOverridden[$reflContainer->getName()] = true;
    }

    private static function overrideGeneratedContainer(ReflectionClass $reflContainer, string $cacheDir, bool $isDebug): void
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
        $codeExtractor = new ContainerSourceCodeExtractor($containerSource);
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

        if (!$reflContainer->hasMethod('createProxy')) {
            $methodsCodes[] = self::generateOverriddenCreateProxy();
        }

        $methodsCodes[] = self::generateOverridenLoad($reflContainer);

        foreach ($methods as $method) {
            $methodName = $method->getName();

            if (isset($ignoredMethods[$methodName]) || 0 !== strpos($methodName, 'get')) {
                continue;
            }

            $methodsCodes[] = self::generateOverriddenGetter($method, $codeExtractor);
        }

        $namespace = $reflContainer->getNamespaceName();
        $modifierClassToUse = __CLASS__;
        $methodsCode = implode(PHP_EOL.PHP_EOL, $methodsCodes);
        $newContainerSource = <<<EOF
            <?php

            namespace $namespace;

            use $modifierClassToUse;

            class $containerClass extends $overriddenClass
            {
                protected \$lazyInitializedShared = [];

            $methodsCode
            }
            EOF;

        $fs->copy($containerFile, $overriddenFile);
        $fs->dumpFile($overriddenFile, $overriddenSource);
        $fs->dumpFile($containerFile, $newContainerSource);
        self::overrideCachedEntrypoint($fs, $cacheDir, $containerClass, $overriddenFqcn, $isDebug);
    }

    private static function generateOverriddenCreateProxy(): string
    {
        return <<<EOF
                        protected function createProxy(\$class, \Closure \$factory)
                        {
                            \$lock = self::\$locking->acquireContainerLock();

                            try {
                                \$return = parent::createProxy(\$class, \$factory);
                            } finally {
                                \$lock->release();
                            }

                            return \$return;
                        }
            EOF;
    }

    private static function generateOverridenLoad(ReflectionClass $reflContainer): string
    {
        $loadRefl = $reflContainer->getMethod('load');

        if (Container::class == $loadRefl->getDeclaringClass()->getName()) {
            return self::generateOverrideOriginalContainerLoad();
        }

        return self::generateOverridenGeneratedLoad();
    }

    private static function generateOverrideOriginalContainerLoad(): string
    {
        return <<<EOF
                protected function load(string \$file)
                {
                    \$lock = self::\$locking->acquireContainerLock();

                    try {
                        \$overriddenLoad = str_replace('.php', '__Overridden.php', \$file);
                        require_once \$overriddenLoad;

                        \$return = parent::load(\$file);
                    } finally {
                        \$lock->release();
                    }

                    return \$return;
                }
            EOF;
    }

    private static function generateOverridenGeneratedLoad(): string
    {
        return <<<EOF
                protected function load(\$file, \$lazyLoad = true)
                {
                    \$lock = self::\$locking->acquireContainerLock();

                    try {
                        \$fileToLoad = \$file;
                        \$class = self::\$buildContainerNs.'\\\\'.\$file;
                        if ('.' === \$file[-4]) {
                            \$class = substr(\$class, 0, -4);
                        } else {
                            \$fileToLoad .= '.php';
                        }

                        \$overriddenLoad = str_replace('.php', '__Overridden.php', \$fileToLoad);
                        require_once \$overriddenLoad;

                        \$return = parent::load(\$file, \$lazyLoad);
                    } finally {
                        \$lock->release();
                    }

                    return \$return;
                }
            EOF;
    }

    private static function overrideCachedEntrypoint(Filesystem $fs, string $cacheDir, string $containerClass, string $overriddenFqcn, bool $isDebug): void
    {
        $cache = new ConfigCache($cacheDir.'/'.$containerClass.'.php', $isDebug);
        $cachePath = $cache->getPath();

        if (!file_exists($cachePath)) {
            throw new \RuntimeException('Generated cached entry point file is missing.');
        }

        $content = file_get_contents($cachePath);
        $overriddenFile = str_replace('\\', DIRECTORY_SEPARATOR, $overriddenFqcn).'.php';

        $header = <<<EOF
            <?php

            EOF;

        $newHeader = <<<EOF
            <?php

            require_once __DIR__.'/$overriddenFile';

            EOF;

        $replacedContent = str_replace($header, $newHeader, $content);
        $fs->dumpFile($cachePath, $replacedContent);
    }

    private static function generateOverriddenGetter(ReflectionMethod $method, ContainerSourceCodeExtractor $extractor): ?string
    {
        $methodName = $method->getName();
        $internals = $extractor->getContainerInternalsForMethod($method);

        if (isset($internals['type']) && 'factories' === $internals['type']) {
            $internals = [];
        }

        return $method->getNumberOfParameters() > 0 ?
            self::generateLazyGetter($methodName, $internals) : self::generateCasualGetter($methodName, $internals);
    }

    private static function generateLazyGetter(string $methodName, array $internals): string
    {
        $sharedCheck = PHP_EOL;

        if (!empty($internals)) {
            $arrayKey = "['{$internals['key']}']".(isset($internals['key2']) ? "['{$internals['key2']}']" : '');
            $sharedCheck = <<<EOF
                                        if (isset(\$this->{$internals['type']}{$arrayKey})) {
                                            if (\$lazyLoad) {
                                                return \$this->{$internals['type']}{$arrayKey};
                                            } elseif (\$this->{$internals['type']}{$arrayKey}->isProxyInitialized() && isset(\$this->lazyInitializedShared['$methodName'])) {
                                                return \$this->lazyInitializedShared['$methodName'];
                                            }
                                        }

                EOF;
        }

        return <<<EOF
                    protected function $methodName(\$lazyLoad = true) {
                        // this might be a weird SF container bug or idk... but SF container keeps calling this factory method
                        // with service id
                        if (is_string(\$lazyLoad)) {
                            \$lazyLoad = true;
                        }

            {$sharedCheck}
                        try {
                            \$lock = self::\$locking->acquireContainerLock();
            {$sharedCheck}

                            \$return = parent::{$methodName}(\$lazyLoad);

                            if (!\$lazyLoad) \$this->lazyInitializedShared['$methodName'] = \$return;
                        } finally {
                            \$lock->release();
                        }

                        return \$return;
                    }
            EOF;
    }

    private static function generateCasualGetter(string $methodName, array $internals): string
    {
        $sharedCheck = PHP_EOL;

        if (!empty($internals)) {
            $arrayKey = "['{$internals['key']}']".(isset($internals['key2']) ? "['{$internals['key2']}']" : '');
            $sharedCheck = <<<EOF

                                        if (isset(\$this->{$internals['type']}{$arrayKey})) {
                                            return \$this->{$internals['type']}{$arrayKey};
                                        }

                EOF;
        }

        return <<<EOF
                    protected function $methodName() {
            {$sharedCheck}
                        try {
                            \$lock = self::\$locking->acquireContainerLock();
            {$sharedCheck}
                            \$return = parent::{$methodName}();
                        } finally {
                            \$lock->release();
                        }

                        return \$return;
                    }
            EOF;
    }

    private static function overrideGeneratedContainerGetters(ReflectionClass $reflContainer, string $cacheDir): void
    {
        $fs = new Filesystem();
        $containerNamespace = $reflContainer->getNamespaceName();
        $containerDirectory = $cacheDir.DIRECTORY_SEPARATOR.$containerNamespace;
        $files = scandir($containerDirectory);
        $filteredFiles = array_filter($files, fn (string $fileName): bool => (0 === strpos($fileName, 'get')));

        foreach ($filteredFiles as $fileName) {
            $class = str_replace('.php', '', $fileName);
            self::generateOverriddenDoInExtension(
                $fs,
                $containerDirectory,
                $fileName,
                $class,
                $containerNamespace
            );
        }
    }

    private static function generateOverriddenDoInExtension(
        Filesystem $fs,
        string $containerDir,
        string $fileToLoad,
        string $class,
        string $namespace
    ): void {
        $fullPath = $containerDir.\DIRECTORY_SEPARATOR.$fileToLoad;

        if (false !== strpos($fullPath, '__Overridden.php') || false !== strpos($class, '__Overridden')) {
            return;
        }

        $fullOverriddenPath = str_replace('.php', '__Overridden.php', $fullPath);

        if (file_exists($fullOverriddenPath)) {
            return;
        }

        $overriddenClass = $class.'__Overridden';
        $overriddenFqcn = $namespace.'\\'.$overriddenClass;
        $origContent = file_get_contents($fullPath);
        $codeExtractor = new ContainerSourceCodeExtractor($origContent);
        $overriddenContent = str_replace($class, $overriddenClass, $origContent);
        $overriddenContent = str_replace('self::do(', 'static::do(', $overriddenContent);
        $fs->rename($fullPath, $fullOverriddenPath, true);
        $fs->dumpFile($fullOverriddenPath, $overriddenContent);
        require_once $fullOverriddenPath;
        $reflClass = new ReflectionClass($overriddenFqcn);
        $reflMethod = $reflClass->getMethod('do');
        $codeExtractor = new ContainerSourceCodeExtractor($overriddenContent);
        $internals = $codeExtractor->getContainerInternalsForMethod($reflMethod, true);
        $sharedCheck = '';

        if (!empty($internals)) {
            $arrayKey = "['{$internals['key']}']".(isset($internals['key2']) ? "['{$internals['key2']}']" : '');
            $sharedCheck = <<<EOF
                if (isset(\$container->{$internals['type']}{$arrayKey})) {
                    if (\$lazyLoad) {
                        return \$container->{$internals['type']}{$arrayKey};
                    } elseif (\$container->{$internals['type']}{$arrayKey}->isProxyInitialized() && isset(\$container->lazyInitializedShared['$overriddenClass'])) {
                        return \$container->lazyInitializedShared['$overriddenClass'];
                    }
                }

                EOF;
        }

        $newContent = <<<EOF
            <?php

            namespace $namespace;

            /**
             * @internal This class has been auto-generated by Swoole bundle.
             */
            class $class extends $overriddenClass
            {
                public static function do(\$container, \$lazyLoad = true)
                {
                    $sharedCheck

                    try {
                        \$lock = \$container::\$locking->acquireContainerLock();

                        $sharedCheck

                        \$return = parent::do(\$container, \$lazyLoad);
                        if (!\$lazyLoad) \$container->lazyInitializedShared['$overriddenClass'] = \$return;
                    } finally {
                        \$lock->release();
                    }

                    return \$return;
                }
            }
            EOF;
        $fs->dumpFile($fullPath, $newContent);

        require_once $fullOverriddenPath;
        require_once $fullPath;
    }

    private static function getIgnoredGetters(): array
    {
        $reflBlockingContainer = new ReflectionClass(BlockingContainer::class);
        $methods = $reflBlockingContainer->getMethods(\ReflectionMethod::IS_PROTECTED);
        $methodNames = array_map(fn (ReflectionMethod $method): string => $method->getName(), $methods);
        $methodNames = array_merge($methodNames, get_class_methods(BlockingContainer::class));
        $getters = array_filter($methodNames, fn (string $methodName): bool => 0 === strpos($methodName, 'get'));
        $getters[] = 'getDefaultParameters';

        return array_flip($getters);
    }
}
