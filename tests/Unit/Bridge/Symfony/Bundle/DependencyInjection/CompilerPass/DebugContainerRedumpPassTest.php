<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\DebugContainerRedumpPass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ContainerBuilderDebugDumpPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(DebugContainerRedumpPass::class)]
final class DebugContainerRedumpPassTest extends TestCase
{
    /**
     * Whatever StatefulServicesPass adds after the framework has already dumped, standing in here for a
     * pool and for the original definition it was built around.
     */
    private const string LATE_SERVICE_ID = 'twig.swoole_coop.wrapped';

    private const string EARLY_SERVICE_ID = 'twig';

    private string $buildDir;

    private string $dumpFile;

    #[Override]
    protected function setUp(): void
    {
        $this->buildDir = sys_get_temp_dir() . '/redump_' . uniqid('', true);
        $this->dumpFile = $this->buildDir . '/TestContainer.xml';
        mkdir($this->buildDir, 0o777, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->buildDir);
    }

    public function testTheDumpIsRewrittenWithWhatWasAddedAfterTheFrameworkWroteIt(): void
    {
        $container = $this->containerAlreadyDumpedByTheFramework(coroutinesEnabled: true);
        self::assertStringNotContainsString(self::LATE_SERVICE_ID, $this->dumpedXml());

        $container->setDefinition(self::LATE_SERVICE_ID, new Definition(self::class));
        (new DebugContainerRedumpPass())->process($container);

        self::assertStringContainsString(self::LATE_SERVICE_ID, $this->dumpedXml());
        self::assertStringContainsString(self::EARLY_SERVICE_ID, $this->dumpedXml());
    }

    /**
     * BuildDebugContainerTrait reads the serialized twin in preference to the XML whenever it is there,
     * so a rewritten XML beside a stale `.ser` would change nothing that `debug:container` prints.
     */
    public function testTheSerializedTwinIsRewrittenAsWell(): void
    {
        $container = $this->containerAlreadyDumpedByTheFramework(coroutinesEnabled: true);
        $serializedTwin = substr_replace($this->dumpFile, '.ser', -4);
        self::assertFileExists($serializedTwin);

        $container->setDefinition(self::LATE_SERVICE_ID, new Definition(self::class));
        (new DebugContainerRedumpPass())->process($container);

        /** @var ContainerBuilder $dumped */
        $dumped = unserialize((string) file_get_contents($serializedTwin), ['allowed_classes' => true]);

        self::assertTrue($dumped->hasDefinition(self::LATE_SERVICE_ID));
    }

    /**
     * Without coroutines StatefulServicesPass changes nothing, so the framework's own dump is already
     * accurate and a second run over the whole container would buy nothing.
     */
    public function testTheDumpIsLeftAloneWhenCoroutineSupportIsDisabled(): void
    {
        $container = $this->containerAlreadyDumpedByTheFramework(coroutinesEnabled: false);

        $container->setDefinition(self::LATE_SERVICE_ID, new Definition(self::class));
        (new DebugContainerRedumpPass())->process($container);

        self::assertStringNotContainsString(self::LATE_SERVICE_ID, $this->dumpedXml());
    }

    public function testTheDumpIsLeftAloneWhenTheCoroutinesParameterIsMissing(): void
    {
        $container = $this->containerAlreadyDumpedByTheFramework(coroutinesEnabled: null);

        $container->setDefinition(self::LATE_SERVICE_ID, new Definition(self::class));
        (new DebugContainerRedumpPass())->process($container);

        self::assertStringNotContainsString(self::LATE_SERVICE_ID, $this->dumpedXml());
    }

    /**
     * A kernel without debug keeps no dump at all - FrameworkExtension only sets the parameter when it
     * is debugging - and asking a ContainerBuilder for a parameter it does not have throws.
     */
    public function testNothingIsWrittenWhenTheKernelKeepsNoDump(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_ENABLED, true);
        $container->setDefinition(self::LATE_SERVICE_ID, new Definition(self::class));

        (new DebugContainerRedumpPass())->process($container);

        self::assertFileDoesNotExist($this->dumpFile);
    }

    public function testNothingIsWrittenWhenTheDumpParameterHoldsNoPath(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(ContainerConstants::PARAM_COROUTINES_ENABLED, true);
        $container->setParameter('debug.container.dump', false);
        $container->setDefinition(self::LATE_SERVICE_ID, new Definition(self::class));

        (new DebugContainerRedumpPass())->process($container);

        self::assertFileDoesNotExist($this->dumpFile);
    }

    /**
     * A container in the state the framework leaves it in at priority -255: one service dumped, the
     * cache fresh, and the dumper primed to return early if it is handed the container again.
     */
    private function containerAlreadyDumpedByTheFramework(?bool $coroutinesEnabled): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('debug.container.dump', $this->dumpFile);

        if ($coroutinesEnabled !== null) {
            $container->setParameter(ContainerConstants::PARAM_COROUTINES_ENABLED, $coroutinesEnabled);
        }

        $container->setDefinition(self::EARLY_SERVICE_ID, new Definition(self::class));
        (new ContainerBuilderDebugDumpPass())->process($container);

        return $container;
    }

    private function dumpedXml(): string
    {
        return (string) file_get_contents($this->dumpFile);
    }
}
