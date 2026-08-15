<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\WebProfiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    ClassModificationProcessor,
    Proxifier,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\WebProfiler\ContentSecurityPolicyHandlerResetter;
use SwooleBundle\SwooleBundle\Bridge\Symfony\WebProfiler\WebProfilerProcessor;
use Symfony\Bundle\WebProfilerBundle\Csp\ContentSecurityPolicyHandler;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(WebProfilerProcessor::class)]
final class WebProfilerProcessorTest extends TestCase
{
    private const string CSP_HANDLER_ID = 'web_profiler.csp.handler';
    private const string CSP_HANDLER_RESETTER_ID = 'swoole_bundle.web_profiler.csp_handler_resetter';

    public function testTheCspHandlerIsPooledWithItsOwnResetter(): void
    {
        $container = $this->containerWithCspHandler();

        $this->process($container);

        self::assertSame(
            [['resetter' => self::CSP_HANDLER_RESETTER_ID]],
            $container->getDefinition(self::CSP_HANDLER_ID)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
        self::assertSame(
            ContentSecurityPolicyHandlerResetter::class,
            $container->getDefinition(self::CSP_HANDLER_RESETTER_ID)->getClass(),
        );
    }

    /**
     * The handler comes with WebProfilerBundle and nothing else registers it.
     */
    public function testAnApplicationWithoutTheWebProfilerIsLeftAlone(): void
    {
        $container = $this->newContainer();

        $this->process($container);

        self::assertFalse($container->hasDefinition(self::CSP_HANDLER_RESETTER_ID));
    }

    /**
     * Compile processors run before StatefulServicesPass acts on the tags, and it is the tag - not a
     * proxifyService() call - that has to be there when it does.
     */
    public function testTheHandlerIsNotProxifiedByTheProcessorItself(): void
    {
        $container = $this->containerWithCspHandler();

        $this->process($container);

        self::assertFalse($container->has(self::CSP_HANDLER_ID . '.swoole_coop.wrapped'));
    }

    private function containerWithCspHandler(): ContainerBuilder
    {
        $container = $this->newContainer();
        $container->register(self::CSP_HANDLER_ID, ContentSecurityPolicyHandler::class);

        return $container;
    }

    private function process(ContainerBuilder $container): void
    {
        (new WebProfilerProcessor())->process(
            $container,
            new Proxifier($container, new ClassModificationProcessor($container)),
        );
    }

    private function newContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_MAX_SVC_INSTANCES, 20);

        return $container;
    }
}
