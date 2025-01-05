<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Exception;
use Generator;
use PixelFederation\DoctrineResettableEmBundle\PixelFederationDoctrineResettableEmBundle;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\SwooleBundle;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Kernel\CoroutinesSupportingKernel;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\CoverageBundle;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\DependencyInjection\{
    CompilerPass\OverrideDoctrineCompilerPass,
};
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\TestBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\Exception\LoaderLoadException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestAppKernel extends Kernel
{
    use MicroKernelTrait;
    use CoroutinesSupportingKernel;

    private const CONFIG_EXTENSIONS = '.{php,xml,yaml,yml}';

    private readonly ?string $overrideProdEnv;

    private ?TestCacheKernel $cacheKernel = null;

    private bool $coverageEnabled;

    private bool $profilerEnabled = false;

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

        if ($environment === 'profiler') {
            $this->profilerEnabled = true;
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

    public function getCacheDir(): string
    {
        return $this->getVarDir() . '/cache/' . $this->environment;
    }

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
        yield new PixelFederationDoctrineResettableEmBundle();

        if ($this->coverageEnabled) {
            yield new CoverageBundle();
        }

        if (!$this->profilerEnabled) {
            return;
        }

        yield new WebProfilerBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__ . '/app';
    }

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
    public function isDebug(): bool
    {
        return (bool) $this->debug;
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new OverrideDoctrineCompilerPass());
    }

    /**
     * @throws LoaderLoadException
     */
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routingFile = 'routing.php';

        if (self::MAJOR_VERSION === 5) { /** @phpstan-ignore identical.alwaysTrue */
            $routingFile = 'routing_54.php';
        }

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

        $confDir = $this->getProjectDir() . '/config';

        $loader->load($confDir . '/*' . self::CONFIG_EXTENSIONS, 'glob');
        if (is_dir($confDir . '/' . $this->environment)) {
            $loader->load($confDir . '/' . $this->environment . '/*' . self::CONFIG_EXTENSIONS, 'glob');
        }

        if ($this->coverageEnabled && $this->environment !== 'cov') {
            $loader->load($confDir . '/cov/**/*' . self::CONFIG_EXTENSIONS, 'glob');
        }

        $this->loadOverrideForProdEnvironment($confDir, $loader);
    }

    private function getVarDir(): string
    {
        return $this->getProjectDir() . '/var';
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
