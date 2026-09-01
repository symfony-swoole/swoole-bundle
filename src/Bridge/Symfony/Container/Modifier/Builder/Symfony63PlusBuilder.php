<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Modifier\Builder;

use Assert\Assertion;
use ReflectionMethod as CoreReflectionMethod;
use RuntimeException;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\BlockingContainer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ContainerSourceCodeExtractor;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use ZEngine\Reflection\ReflectionClass;
use ZEngine\Reflection\ReflectionMethod;

/**
 * @phpstan-import-type ContainerMethodInternals from ContainerSourceCodeExtractor
 */
final class Symfony63PlusBuilder implements Builder
{
    /**
     * Enough of a generated file to reach its `class X extends Y` line. Every file this reads - the
     * container itself and the per-service factories - opens with a namespace, a handful of use
     * statements and a docblock; the include_once calls that make these files long live inside the
     * method body, well past the declaration.
     */
    private const int CLASS_DECLARATION_LOOKAHEAD_BYTES = 2048;

    public function overrideGeneratedContainer(ReflectionClass $reflContainer, string $cacheDir, bool $isDebug): void
    {
        $fs = new Filesystem();

        $cacheDir = $this->getRealCacheDir($fs, $cacheDir);
        $containerFqcn = $reflContainer->getName();
        $overriddenFqcn = $containerFqcn . '_Overridden';
        $classParts = explode('\\', $containerFqcn);
        $containerClass = array_pop($classParts);
        $overriddenClass = $containerClass . '_Overridden';
        $containerFile = $reflContainer->getFileName();

        Assertion::string($containerFile, 'Could not get container file name from reflection.');

        $overriddenFile = $cacheDir . DIRECTORY_SEPARATOR . str_replace(
            '\\',
            DIRECTORY_SEPARATOR,
            $overriddenFqcn
        ) . '.php';

        // Asking whether the live file is already the wrapper, not whether the _Overridden copy exists.
        //
        // The two can disagree, and silently. Symfony re-dumps into the same directory whenever it
        // decides the container is stale - the namespace is derived from the container, so a rebuild
        // lands on the same name - and it writes the plain generated file straight over the wrapper.
        // The _Overridden copy survives, because Symfony knows nothing about it. Keying off that copy
        // therefore reads "already done" for a container that has just lost every mutex this builder
        // put in it, and never repairs it: the application runs on with no lock around first
        // instantiation, for as long as that cache directory lives.
        //
        // Requiring the copy to exist as well, because a wrapper whose parent has been deleted is not
        // a container that works.
        if (file_exists($overriddenFile) && $this->isAlreadyOverridden($containerFile, $overriddenClass)) {
            return;
        }

        if (!$fs->exists($containerFile)) {
            throw new RuntimeException(sprintf('Container file "%s" does not exist.', $containerFile));
        }

        $containerSource = file_get_contents($containerFile);

        if ($containerSource === false) {
            throw new RuntimeException(sprintf('Could not read container file "%s".', $containerFile));
        }

        $codeExtractor = new ContainerSourceCodeExtractor($containerSource);
        $overriddenSource = str_replace('class ' . $containerClass, 'class ' . $overriddenClass, $containerSource);

        // dump opcache.blacklist_filename
        $blacklistFile = implode(
            DIRECTORY_SEPARATOR,
            [$cacheDir, ContainerConstants::PARAM_CACHE_FOLDER, 'opcache', 'blacklist.txt']
        );
        $blacklistFiles = [$containerFile, $overriddenFile];
        $blacklistFileContent = implode(PHP_EOL, $blacklistFiles) . PHP_EOL;
        $fs->dumpFile($blacklistFile, $blacklistFileContent);

        // methods override
        $ignoredMethods = $this->getIgnoredGetters();
        $methods = $reflContainer->getMethods(CoreReflectionMethod::IS_PROTECTED);
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
        $methodsCode = implode(PHP_EOL . PHP_EOL, $methodsCodes);
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

        // No copy before it: dumpFile writes the whole file anyway, and a copy is a second, non-atomic
        // write of content nobody ever reads.
        $fs->dumpFile($overriddenFile, $overriddenSource);
        $fs->dumpFile($containerFile, $newContainerSource);
        $this->overrideCachedEntrypoint($fs, $cacheDir, $containerClass, $overriddenFqcn, $isDebug);
    }

    public function overrideGeneratedContainerGetters(ReflectionClass $reflContainer, string $cacheDir): void
    {
        $fs = new Filesystem();
        $cacheDir = $this->getRealCacheDir($fs, $cacheDir);
        $containerNamespace = $reflContainer->getNamespaceName();
        $containerDirectory = $cacheDir . DIRECTORY_SEPARATOR . $containerNamespace;
        $files = scandir($containerDirectory);

        if ($files === false) {
            throw new RuntimeException(sprintf('Could not read container directory "%s".', $containerDirectory));
        }

        // Ending in .php as well as starting with get, because a directory being written by another process
        // holds its temporary files too - Filesystem::dumpFile() writes `getFooService.php6ee1r0` and
        // renames it into place - and those are gone by the time this would read them.
        $filteredFiles = array_filter(
            $files,
            static fn(string $fileName): bool => str_starts_with($fileName, 'get') && str_ends_with($fileName, '.php'),
        );

        foreach ($filteredFiles as $fileName) {
            $class = str_replace('.php', '', $fileName);
            $this->generateOverriddenDoInExtension($fs, $containerDirectory, $fileName, $class, $containerNamespace);
        }
    }

    private function generateOverriddenCreateProxy(): string
    {
        return <<<'EOF'
                        protected function createProxy($class, \Closure $factory)
                        {
                            self::$mutex->acquire();

                            try {
                                $return = parent::createProxy($class, $factory);
                            } finally {
                                self::$mutex->release();
                            }

                            return $return;
                        }
            EOF;
    }

    private function generateOverridenLoad(ReflectionClass $reflContainer): string
    {
        $loadRefl = $reflContainer->getMethod('load');

        if ($loadRefl->getDeclaringClass()->getName() === Container::class) {
            return self::generateOverrideOriginalContainerLoad();
        }

        return self::generateOverridenGeneratedLoad();
    }

    private static function generateOverrideOriginalContainerLoad(): string
    {
        return <<<'EOF'
                protected function load(string $file)
                {
                    self::$mutex->acquire();

                    try {
                        $overriddenLoad = str_replace('.php', '__Overridden.php', $file);
                        require_once $overriddenLoad;

                        $return = parent::load($file);
                    } finally {
                        self::$mutex->release();
                    }

                    return $return;
                }
            EOF;
    }

    private static function generateOverridenGeneratedLoad(): string
    {
        return <<<'EOF'
                protected function load($file, $lazyLoad = true): mixed
                {
                    self::$mutex->acquire();

                    try {
                        $fileToLoad = $file;
                        $class = self::$buildContainerNs.'\\'.$file;
                        if ('.' === $file[-4]) {
                            $class = substr($class, 0, -4);
                        } else {
                            $fileToLoad .= '.php';
                        }

                        $overriddenLoad = str_replace('.php', '__Overridden.php', $fileToLoad);
                        require_once $overriddenLoad;

                        $return = parent::load($file, $lazyLoad);
                    } finally {
                        self::$mutex->release();
                    }

                    return $return;
                }
            EOF;
    }

    private function overrideCachedEntrypoint(
        Filesystem $fs,
        string $cacheDir,
        string $containerClass,
        string $overriddenFqcn,
        bool $isDebug,
    ): void {
        $cache = new ConfigCache($cacheDir . '/' . $containerClass . '.php', $isDebug);
        $cachePath = $cache->getPath();

        if (!file_exists($cachePath)) {
            throw new RuntimeException('Generated cached entry point file is missing.');
        }

        $content = file_get_contents($cachePath);

        if ($content === false) {
            throw new RuntimeException('Could not read generated cached file.');
        }

        $overriddenFile = str_replace('\\', DIRECTORY_SEPARATOR, $overriddenFqcn) . '.php';

        $header = <<<'EOF'
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

        if (isset($internals['type']) && $internals['type'] === 'factories') {
            $internals = [];
        }

        return $method->getNumberOfParameters() > 0
            ? $this->generateLazyGetter($methodName, $internals)
            : $this->generateCasualGetter($methodName, $internals);
    }

    /**
     * @param ContainerMethodInternals $internals
     */
    private function generateLazyGetter(string $methodName, array $internals): string
    {
        $sharedCheck = PHP_EOL;

        if (!empty($internals)) {
            Assertion::keyExists($internals, 'key');
            Assertion::keyExists($internals, 'type');

            $arrayKey = "['{$internals['key']}']" . (isset($internals['key2']) ? "['{$internals['key2']}']" : '');
            // See the note in buildProxifiedServiceFactory(): only the literal `true` asks for the lazy
            // instance. A native lazy ghost's initializer passes the proxy object here and needs the
            // factory to run __construct() on it, and an object is truthy.
            $sharedCheck = <<<EOF
                                        if (isset(\$this->{$internals['type']}{$arrayKey})) {
                                            if (\$lazyLoad === true) {
                                                return \$this->{$internals['type']}{$arrayKey};
                                            } elseif (\$lazyLoad === false
                                                && isset(\$this->lazyInitializedShared['$methodName'])
                                            ) {
                                                return \$this->lazyInitializedShared['$methodName'];
                                            }
                                        }

                EOF;
        }

        return <<<EOF
                    protected static function $methodName(\$container, \$lazyLoad = true) {
                        // this might be a weird SF container bug or idk... but SF container keeps calling
                        // this factory method with service id
                        if (is_string(\$lazyLoad)) {
                            \$lazyLoad = true;
                        }

            {$sharedCheck}
                        try {
                            self::\$mutex->acquire();
            {$sharedCheck}

                            \$return = parent::{$methodName}(\$container, \$lazyLoad);

                            if (\$lazyLoad !== true) \$this->lazyInitializedShared['$methodName'] = \$return;
                        } finally {
                            self::\$mutex->release();
                        }

                        return \$return;
                    }
            EOF;
    }

    /**
     * @param ContainerMethodInternals $internals
     */
    private function generateCasualGetter(string $methodName, array $internals): string
    {
        $sharedCheck = PHP_EOL;

        if (!empty($internals)) {
            Assertion::keyExists($internals, 'key');
            Assertion::keyExists($internals, 'type');

            $arrayKey = "['{$internals['key']}']" . (isset($internals['key2']) ? "['{$internals['key2']}']" : '');
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
        string $namespace,
    ): void {
        $fullPath = $containerDir . DIRECTORY_SEPARATOR . $fileToLoad;

        if (str_contains($fullPath, '__Overridden.php') || str_contains($class, '__Overridden')) {
            return;
        }

        $fullOverriddenPath = str_replace('.php', '__Overridden.php', $fullPath);
        $overriddenClass = $class . '__Overridden';

        // Same test as in overrideGeneratedContainer(), for the same reason and the same failure: a
        // re-dump overwrites these factories with the plain generated versions and leaves their
        // __Overridden copies behind.
        if (file_exists($fullOverriddenPath) && $this->isAlreadyOverridden($fullPath, $overriddenClass)) {
            return;
        }
        $overriddenFqcn = $namespace . '\\' . $overriddenClass;
        $origContent = file_get_contents($fullPath);

        if ($origContent === false) {
            throw new RuntimeException('Could not read original generated cached file.');
        }

        $codeExtractor = new ContainerSourceCodeExtractor($origContent);
        $overriddenContent = str_replace($class, $overriddenClass, $origContent);
        $overriddenContent = str_replace('self::do(', 'static::do(', $overriddenContent);
        // Written, not renamed into place. The rename that used to be here moved the generated factory out
        // of the way and was then overwritten by this same dump, so its only lasting effect was a moment in
        // which the path Symfony's container includes did not exist at all - long enough for another process
        // to fail on it. The factory below replaces it in one atomic write instead.
        $fs->dumpFile($fullOverriddenPath, $overriddenContent);
        require_once $fullOverriddenPath;
        $reflClass = new ReflectionClass($overriddenFqcn);
        /** @var ReflectionMethod $reflMethod */
        $reflMethod = $reflClass->getMethod('do');
        $codeExtractor = new ContainerSourceCodeExtractor($overriddenContent);
        $internals = $codeExtractor->getContainerInternalsForMethod($reflMethod, true);
        $sharedCheck = '';

        if (!empty($internals)) {
            Assertion::keyExists($internals, 'key');
            Assertion::keyExists($internals, 'type');

            $arrayKey = "['{$internals['key']}']" . (isset($internals['key2']) ? "['{$internals['key2']}']" : '');
            // Only the literal `true` means "hand back the lazy instance". Symfony calls the factory a
            // third way as well: a native lazy ghost's initializer passes the *proxy object* as
            // $lazyLoad and expects the factory to run __construct() on it. An object is truthy, so a
            // plain if ($lazyLoad) returned the still-uninitialized ghost and the constructor never ran,
            // leaving the promoted properties unset - "Typed property X must not be accessed before
            // initialization", thrown from inside the service's own methods.
            $sharedCheck = <<<EOF
                if (isset(\$container->{$internals['type']}{$arrayKey})) {
                    if (\$lazyLoad === true) {
                        return \$container->{$internals['type']}{$arrayKey};
                    } elseif (\$lazyLoad === false && isset(\$container->lazyInitializedShared['$overriddenClass'])) {
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
                    // Symfony sometimes calls the factory with the service id instead of a flag; it
                    // means the same as `true`, and normalising here keeps the strict comparisons
                    // below honest about the one case that is genuinely different - a lazy ghost
                    // handing us the proxy object to construct.
                    if (is_string(\$lazyLoad)) {
                        \$lazyLoad = true;
                    }

                    $sharedCheck

                    try {
                        \$container::\$mutex->acquire();

                        $sharedCheck

                        \$return = parent::do(\$container, \$lazyLoad);
                        if (\$lazyLoad !== true) \$container->lazyInitializedShared['$overriddenClass'] = \$return;
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

    /**
     * Whether the file at this path is the wrapper this builder writes, rather than the plain file
     * Symfony generated.
     *
     * Told apart by what the class extends: everything this builder writes extends the `_Overridden`
     * copy it made, and nothing Symfony generates does - its factories extend the container, and the
     * container extends {@see BlockingContainer}.
     *
     * Read as a bounded prefix rather than whole: a container directory holds a file per service, and
     * this runs over all of them on every boot.
     */
    private function isAlreadyOverridden(string $filePath, string $overriddenClass): bool
    {
        $head = @file_get_contents($filePath, false, null, 0, self::CLASS_DECLARATION_LOOKAHEAD_BYTES);

        if ($head === false) {
            return false;
        }

        return str_contains($head, 'extends ' . $overriddenClass);
    }

    /**
     * @return array<string>
     */
    private function getIgnoredGetters(): array
    {
        $reflBlockingContainer = new ReflectionClass(BlockingContainer::class);
        $methods = $reflBlockingContainer->getMethods(CoreReflectionMethod::IS_PROTECTED);
        $methodNames = array_map(static fn(ReflectionMethod $method): string => $method->getName(), $methods);
        $methodNames = array_merge($methodNames, get_class_methods(BlockingContainer::class));
        $getters = array_filter(
            $methodNames,
            static fn(string $methodName): bool => str_starts_with($methodName, 'get')
        );
        $getters[] = 'getDefaultParameters';

        return array_flip($getters);
    }

    private function getRealCacheDir(Filesystem $fs, string $cacheDir): string
    {
        $cacheDirTmp = substr($cacheDir, 0, -1) . '_';

        if ($fs->exists($cacheDirTmp)) {
            $cacheDir = $cacheDirTmp;
        }

        return $cacheDir;
    }
}
