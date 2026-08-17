<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\HttpClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    ClassModificationProcessor,
    Proxifier,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpClient\HttpClientProcessor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\TraceableHttpClient;

#[CoversClass(HttpClientProcessor::class)]
final class HttpClientProcessorTest extends TestCase
{
    private const string DEBUG_CLIENT_ID = '.debug.http_client';

    public function testTheTracedClientIsPooled(): void
    {
        $container = $this->newContainer();
        $container->register(self::DEBUG_CLIENT_ID, TraceableHttpClient::class);

        $this->process($container);

        self::assertSame(
            [[]],
            $container->getDefinition(self::DEBUG_CLIENT_ID)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * An application registering several http clients gets a traced client each, and every one of them
     * accumulates traces of its own.
     */
    public function testEveryTracedClientInTheContainerIsPooled(): void
    {
        $container = $this->newContainer();
        $container->register(self::DEBUG_CLIENT_ID, TraceableHttpClient::class);
        $container->register('.debug.saltedge.client', TraceableHttpClient::class);

        $this->process($container);

        foreach ([self::DEBUG_CLIENT_ID, '.debug.saltedge.client'] as $id) {
            self::assertSame(
                [[]],
                $container->getDefinition($id)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
                sprintf('Expected %s to be pooled.', $id),
            );
        }
    }

    /**
     * The point of matching on the class: HttpClientPass tags the traced client `kernel.reset`, but
     * DecoratorServicePass hands the tags of a decoration chain to whichever decorator ends up
     * outermost, so by the time this runs the traced client can carry no tags at all.
     */
    public function testATracedClientStrippedOfItsResetTagIsStillPooled(): void
    {
        $container = $this->newContainer();
        $container->register(self::DEBUG_CLIENT_ID, TraceableHttpClient::class)->clearTags();

        $this->process($container);

        self::assertTrue(
            $container->getDefinition(self::DEBUG_CLIENT_ID)->hasTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * The rest of the chain is somebody else's business: the transport is pooled through its own
     * `kernel.reset` tag, and nothing here should give it a second one.
     */
    public function testTheClientsAroundItAreLeftAlone(): void
    {
        $container = $this->newContainer();
        $container->register('http_client.transport', CurlHttpClient::class);

        $this->process($container);

        self::assertSame(
            [],
            $container->getDefinition('http_client.transport')->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * A container holds definitions for classes the application cannot load - an optional integration
     * whose package is not installed, a validator whose parent lives behind a suggest. Asking each
     * definition what its class *is* rather than comparing the name autoloads all of them, and the
     * first one that will not load takes the whole compilation down with it.
     */
    public function testADefinitionNamingAnUnloadableClassDoesNotBreakTheCompilation(): void
    {
        $container = $this->newContainer();
        $container->register('some.optional.integration', 'App\\Vendor\\That\\Is\\Not\\Installed');
        $container->register(self::DEBUG_CLIENT_ID, TraceableHttpClient::class);

        $this->process($container);

        self::assertTrue(
            $container->getDefinition(self::DEBUG_CLIENT_ID)->hasTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    public function testAnApplicationWithoutTheHttpClientProfilerIsLeftAlone(): void
    {
        $container = $this->newContainer();

        $this->expectNotToPerformAssertions();

        $this->process($container);
    }

    /**
     * Tagging twice would have StatefulServicesPass read a second, identical tag off the definition -
     * harmless, but the processor should be safe to run over a container it has already seen.
     */
    public function testAClientThatIsAlreadyPooledIsNotTaggedAgain(): void
    {
        $container = $this->newContainer();
        $container->register(self::DEBUG_CLIENT_ID, TraceableHttpClient::class)
            ->addTag(ContainerConstants::TAG_STATEFUL_SERVICE, ['limit' => 5]);

        $this->process($container);

        self::assertSame(
            [['limit' => 5]],
            $container->getDefinition(self::DEBUG_CLIENT_ID)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    private function process(ContainerBuilder $container): void
    {
        (new HttpClientProcessor())->process(
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
