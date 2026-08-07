<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Exception;
use Generator;
use Override;
use SwooleBundle\ResetterBundle\SwooleBundleResetterBundle;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\SwooleBundle;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Kernel\CoroutinesSupportingKernel;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\CoverageBundle;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\DependencyInjection\{
    CompilerPass\OverrideDoctrineCompilerPass,
};
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\TestBundle;
use SwooleBundle\SwooleBundle\Tests\Helper\TestToken;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\Exception\LoaderLoadException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestAppKernel extends Kernel implements WarmableInterface
{
    use CoroutinesSupportingKernel;
    use MicroKernelTrait;

    private const string CONFIG_EXTENSIONS = '.{php,xml,yaml,yml}';

    private readonly ?string $overrideProdEnv;

    private ?TestCacheKernel $cacheKernel = null;

    private bool $coverageEnabled;

    private bool $profilerEnabled = false;

    private bool $securityEnabled = false;

    public function __construct(string $environment, bool $debug, ?string $overrideProdEnv = null)
    {
        if (mb_substr($environment, -4, 4) === '_cov') {
            $environment = mb_substr($environment, 0, -4);
            $this->coverageEnabled = true;
        } elseif ($environment === 'cov') {
            $this->coverageEnabled = true;
        } else {
            $this->coverageEnabled = false;
        }

        if ($environment === 'profiler' || $environment === 'coroutines_profiler') {
            $this->profilerEnabled = true;
        }

        // symfony/security-bundle is only needed to exercise the real, Symfony-built
        // security.event_dispatcher.<firewall> definitions (see coroutines_security/security.php) —
        // registering it only for this environment keeps every other test unaffected.
        if ($environment === 'coroutines_security') {
            $this->securityEnabled = true;
        }

        $enableSessionCache = false;

        if (mb_substr($environment, -11, 11) === '_http_cache') {
            $environment = mb_substr($environment, 0, -11);
            $enableSessionCache = true;
        }

        if ($overrideProdEnv !== null) {
            $overrideProdEnv = trim($overrideProdEnv);
        }

        $this->overrideProdEnv = $overrideProdEnv;

        parent::__construct($environment, $debug);

        if (!$enableSessionCache) {
            return;
        }

        $this->cacheKernel = new TestCacheKernel($this);
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->getVarDir() . '/cache/' . $this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->getVarDir() . '/log';
    }

    public function registerBundles(): Generator
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new MonologBundle();
        yield new SwooleBundle();
        yield new TestBundle();
        yield new DoctrineBundle();
        yield new DoctrineMigrationsBundle();
        yield new SwooleBundleResetterBundle();

        if ($this->coverageEnabled) {
            yield new CoverageBundle();
        }

        if ($this->securityEnabled) {
            yield new SecurityBundle();
        }

        if (!$this->profilerEnabled) {
            return;
        }

        yield new WebProfilerBundle();
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__ . '/app';
    }

    #[Override]
    public function handle(
        Request $request,
        int $type = HttpKernelInterface::MAIN_REQUEST,
        bool $catch = true,
    ): Response {
        // Use CacheKernel if available.
        if ($this->cacheKernel !== null) {
            // Prevent endless loop. Unset $this->cacheKernel, handle the request and then restore it.
            $cacheKernel = $this->cacheKernel;
            $this->cacheKernel = null;
            $response = $cacheKernel->handle($request, $type, $catch);
            $this->cacheKernel = $cacheKernel;

            return $response;
        }

        return parent::handle($request, $type, $catch);
    }

    /**
     * This should always return bool, but we need to coerce it depending on the Symfony version in use.
     */
    #[Override]
    public function isDebug(): bool
    {
        return (bool) $this->debug;
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new OverrideDoctrineCompilerPass());
        $this->doNotDumpTheConfigReference($container);
    }

    /**
     * @throws LoaderLoadException
     */
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routingFile = 'routing.php';
        $routes->import($this->getProjectDir() . '/' . $routingFile);

        $envRoutingFile = $this->getProjectDir() . '/config/' . $this->environment . '/routing/routing.php';

        if (!file_exists($envRoutingFile)) {
            return;
        }

        $routes->import($envRoutingFile);
    }

    /**
     * @throws Exception
     */
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->setParameter('bundle.root_dir', dirname(__DIR__, 3));
        // Fixtures that put a file next to the caches and logs have to follow the worker's var
        // directory rather than assume "var" - see self::getVarDir().
        $container->setParameter('test.var_dir', $this->getVarDir());

        $confDir = $this->getProjectDir() . '/config';

        // Load all config files except reference.php
        $configFiles = glob($confDir . '/*.php');

        if ($configFiles === false) {
            $configFiles = [];
        }

        foreach ($configFiles as $file) {
            if (basename($file) === 'reference.php') {
                // Parallel workers race each other to it; losing the race is not worth a warning.
                @unlink($file);

                continue;
            }

            $loader->load($file);
        }

        if (is_dir($confDir . '/' . $this->environment)) {
            $loader->load($confDir . '/' . $this->environment . '/*' . self::CONFIG_EXTENSIONS, 'glob');
        }

        if ($this->coverageEnabled && $this->environment !== 'cov') {
            $loader->load($confDir . '/cov/**/*' . self::CONFIG_EXTENSIONS, 'glob');
        }

        $this->loadOverrideForProdEnvironment($confDir, $loader);
    }

    /**
     * Drops the compiler pass that writes config/reference.php.
     *
     * That file is an IDE convenience - generated types for autocompleting the php config files - and no
     * test reads it. FrameworkBundle writes it on every debug compile, into the single config directory
     * all the parallel workers share, and PhpFileLoader includes it while compiling: one worker rewriting
     * it while another is part-way through including it ends the second one with "Cannot redeclare class
     * AppReference". Not generating it is simpler than racing over it, which is why the fixtures used to
     * delete it on the way past.
     *
     * Matched by name rather than by class, so this keeps working on Symfony versions that have no such
     * pass - and it is marked @internal, which is reason enough not to import it.
     */
    private function doNotDumpTheConfigReference(ContainerBuilder $container): void
    {
        $passConfig = $container->getCompilerPassConfig();

        $passConfig->setBeforeOptimizationPasses(array_values(array_filter(
            $passConfig->getBeforeOptimizationPasses(),
            static fn(CompilerPassInterface $pass): bool => $pass::class
                !== 'Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\PhpConfigReferenceDumpPass',
        )));
    }

    /**
     * Caches and logs land in a directory of this worker's own - "var" on its own, "var-2" for the
     * second ParaTest worker and so on. Feature tests wipe this directory between tests, and without
     * the split one worker would pull the compiled container out from under another mid-boot.
     */
    private function getVarDir(): string
    {
        return $this->getProjectDir() . '/var' . TestToken::suffix();
    }

    private function loadOverrideForProdEnvironment(string $confDir, LoaderInterface $loader): void
    {
        if ($this->environment !== 'prod') {
            return;
        }

        $envPackageConfigurationDir = sprintf('%s/%s', $confDir, $this->overrideProdEnv);

        if (!is_dir($envPackageConfigurationDir)) {
            return;
        }

        $loader->load($envPackageConfigurationDir . '/*' . self::CONFIG_EXTENSIONS, 'glob');
    }
}
