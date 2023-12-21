<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Container\Modifier\Builder;

use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use K911\Swoole\Bridge\Symfony\Container\BlockingContainer;
use K911\Swoole\Bridge\Symfony\Container\ContainerSourceCodeExtractor;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use ZEngine\Reflection\ReflectionClass;
use ZEngine\Reflection\ReflectionMethod;

final class Symfony63PlusBuilder implements Builder
{
    public function overrideGeneratedContainer(ReflectionClass $reflContainer, string $cacheDir, bool $isDebug): void
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

        $containerSource = file_get_contents($containerFile);

        if (false === $containerSource) {
            throw new \RuntimeException(sprintf('Could not read container file "%s".', $containerFile));
        }

        $codeExtractor = new ContainerSourceCodeExtractor($containerSource);
        $overriddenSource = str_replace('class '.$containerClass, 'class '.$overriddenClass, $containerSource);

        // dump opcache.blacklist_filename
        $blacklistFile = $cacheDir.DIRECTORY_SEPARATOR.ContainerConstants::PARAM_CACHE_FOLDER.DIRECTORY_SEPARATOR.'opcache'.DIRECTORY_SEPARATOR.'blacklist.txt';
        $blacklistFiles = [$containerFile, $overriddenFile];
        $blacklistFileContent = implode(PHP_EOL, $blacklistFiles).PHP_EOL;
        $fs->dumpFile($blacklistFile, $blacklistFileContent);

        // methods override
        $ignoredMethods = $this->getIgnoredGetters();
        $methods = $reflContainer->getMethods(\ReflectionMethod::IS_PROTECTED);
        $methodsCodes = [];

        if (!$reflContainer->hasMethod('createProxy')) {
            $methodsCodes[] = $this->generateOverriddenCreateProxy();
        }

        $methodsCodes[] = $this->generateOverridenLoad($reflContainer);

        foreach ($methods as $method) {
            $methodName = $method->getName();

            if (isset($ignoredMethods[$methodName]) || !str_starts_with($methodName, 'get')) {
                continue;
            }

            $methodsCodes[] = $this->generateOverriddenGetter($method, $codeExtractor);
        }

        $namespace = $reflContainer->getNamespaceName();
        $modifierClassToUse = self::class;
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
        $this->overrideCachedEntrypoint($fs, $cacheDir, $containerClass, $overriddenFqcn, $isDebug);
    }

    public function overrideGeneratedContainerGetters(ReflectionClass $reflContainer, string $cacheDir): void
    {
        $fs = new Filesystem();
        $containerNamespace = $reflContainer->getNamespaceName();
        $containerDirectory = $cacheDir.DIRECTORY_SEPARATOR.$containerNamespace;
        $files = scandir($containerDirectory);

        if (false === $files) {
            throw new \RuntimeException(sprintf('Could not read container directory "%s".', $containerDirectory));
        }

        $filteredFiles = array_filter($files, fn (string $fileName): bool => str_starts_with($fileName, 'get'));

        foreach ($filteredFiles as $fileName) {
            $class = str_replace('.php', '', $fileName);
            $this->generateOverriddenDoInExtension(
                $fs,
                $containerDirectory,
                $fileName,
                $class,
                $containerNamespace
            );
        }
    }

    private function generateOverriddenCreateProxy(): string
    {
        return <<<EOF
                        protected function createProxy(\$class, \Closure \$factory)
                        {
                            self::\$mutex->acquire();

                            try {
                                \$return = parent::createProxy(\$class, \$factory);
                            } finally {
                                self::\$mutex->release();
                            }

                            return \$return;
                        }
            EOF;
    }

    private function generateOverridenLoad(ReflectionClass $reflContainer): string
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
                    self::\$mutex->acquire();

                    try {
                        \$overriddenLoad = str_replace('.php', '__Overridden.php', \$file);
                        require_once \$overriddenLoad;

                        \$return = parent::load(\$file);
                    } finally {
                        self::\$mutex->release();
                    }

                    return \$return;
                }
            EOF;
    }

    private static function generateOverridenGeneratedLoad(): string
    {
        return <<<EOF
                protected function load(\$file, \$lazyLoad = true): mixed
                {
                    self::\$mutex->acquire();

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
                        self::\$mutex->release();
                    }

                    return \$return;
                }
            EOF;
    }

    private function overrideCachedEntrypoint(Filesystem $fs, string $cacheDir, string $containerClass, string $overriddenFqcn, bool $isDebug): void
    {
        $cache = new ConfigCache($cacheDir.'/'.$containerClass.'.php', $isDebug);
        $cachePath = $cache->getPath();

        if (!file_exists($cachePath)) {
            throw new \RuntimeException('Generated cached entry point file is missing.');
        }

        $content = file_get_contents($cachePath);

        if (false === $content) {
            throw new \RuntimeException('Could not read generated cached file.');
        }

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

    private function generateOverriddenGetter(ReflectionMethod $method, ContainerSourceCodeExtractor $extractor): string
    {
        $methodName = $method->getName();
        $internals = $extractor->getContainerInternalsForMethod($method);

        if (isset($internals['type']) && 'factories' === $internals['type']) {
            $internals = [];
        }

        return $method->getNumberOfParameters() > 0 ?
            $this->generateLazyGetter($methodName, $internals) :
            $this->generateCasualGetter($methodName, $internals);
    }

    private function generateLazyGetter(string $methodName, array $internals): string
    {
        $sharedCheck = PHP_EOL;

        if (!empty($internals)) {
            $arrayKey = "['{$internals['key']}']".(isset($internals['key2']) ? "['{$internals['key2']}']" : '');
            $sharedCheck = <<<EOF
                                        if (isset(\$this->{$internals['type']}{$arrayKey})) {
                                            if (\$lazyLoad) {
                                                return \$this->{$internals['type']}{$arrayKey};
                                            } elseif (isset(\$this->lazyInitializedShared['$methodName'])) {
                                                return \$this->lazyInitializedShared['$methodName'];
                                            }
                                        }

                EOF;
        }

        return <<<EOF
                    protected static function $methodName(\$container, \$lazyLoad = true) {
                        // this might be a weird SF container bug or idk... but SF container keeps calling this factory method
                        // with service id
                        if (is_string(\$lazyLoad)) {
                            \$lazyLoad = true;
                        }

            {$sharedCheck}
                        try {
                            self::\$mutex->acquire();
            {$sharedCheck}

                            \$return = parent::{$methodName}(\$container, \$lazyLoad);

                            if (!\$lazyLoad) \$this->lazyInitializedShared['$methodName'] = \$return;
                        } finally {
                            self::\$mutex->release();
                        }

                        return \$return;
                    }
            EOF;
    }

    private function generateCasualGetter(string $methodName, array $internals): string
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
                    protected static function $methodName(\$container) {
            {$sharedCheck}
                        try {
                            self::\$mutex->acquire();
            {$sharedCheck}
                            \$return = parent::{$methodName}(\$container);
                        } finally {
                            self::\$mutex->release();
                        }

                        return \$return;
                    }
            EOF;
    }

    private function generateOverriddenDoInExtension(
        Filesystem $fs,
        string $containerDir,
        string $fileToLoad,
        string $class,
        string $namespace
    ): void {
        $fullPath = $containerDir.\DIRECTORY_SEPARATOR.$fileToLoad;

        if (str_contains($fullPath, '__Overridden.php') || str_contains($class, '__Overridden')) {
            return;
        }

        $fullOverriddenPath = str_replace('.php', '__Overridden.php', $fullPath);

        if (file_exists($fullOverriddenPath)) {
            return;
        }

        $overriddenClass = $class.'__Overridden';
        $overriddenFqcn = $namespace.'\\'.$overriddenClass;
        $origContent = file_get_contents($fullPath);

        if (false === $origContent) {
            throw new \RuntimeException('Could not read original generated cached file.');
        }

        $codeExtractor = new ContainerSourceCodeExtractor($origContent);
        $overriddenContent = str_replace($class, $overriddenClass, $origContent);
        $overriddenContent = str_replace('self::do(', 'static::do(', $overriddenContent);
        $fs->rename($fullPath, $fullOverriddenPath, true);
        $fs->dumpFile($fullOverriddenPath, $overriddenContent);
        require_once $fullOverriddenPath;
        $reflClass = new ReflectionClass($overriddenFqcn);
        /** @var ReflectionMethod $reflMethod */
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
                    } elseif (isset(\$container->lazyInitializedShared['$overriddenClass'])) {
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
                        \$container::\$mutex->acquire();

                        $sharedCheck

                        \$return = parent::do(\$container, \$lazyLoad);
                        if (!\$lazyLoad) \$container->lazyInitializedShared['$overriddenClass'] = \$return;
                    } finally {
                        \$container::\$mutex->release();
                    }

                    return \$return;
                }
            }
            EOF;
        $fs->dumpFile($fullPath, $newContent);

        require_once $fullOverriddenPath;
        require_once $fullPath;
    }

    private function getIgnoredGetters(): array
    {
        $reflBlockingContainer = new ReflectionClass(BlockingContainer::class);
        $methods = $reflBlockingContainer->getMethods(\ReflectionMethod::IS_PROTECTED);
        $methodNames = array_map(fn (ReflectionMethod $method): string => $method->getName(), $methods);
        $methodNames = array_merge($methodNames, get_class_methods(BlockingContainer::class));
        $getters = array_filter($methodNames, fn (string $methodName): bool => str_starts_with($methodName, 'get'));
        $getters[] = 'getDefaultParameters';

        return array_flip($getters);
    }
}
