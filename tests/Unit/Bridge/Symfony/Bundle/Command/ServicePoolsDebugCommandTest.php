<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\Command;

use Override;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Command\ServicePoolsDebugCommand;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class ServicePoolsDebugCommandTest extends TestCase
{
    public function testListsProxifiedServicesUnderTheSectionOfTheProxifierWhichRenamedThem(): void
    {
        $tester = $this->runCommand(
            serviceIds: ['twig.swoole_coop.wrapped', 'router.swoole_coop.wrapped'],
            removedIds: ['messenger.transport.doctrine.factory.swoole_coop.wrapped_factory' => true],
        );

        $display = $tester->getDisplay();

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Container instantiation (2)', $display);
        self::assertStringContainsString('* router', $display);
        self::assertStringContainsString('* twig', $display);
        self::assertStringContainsString('Unmanaged factory instantiation (1)', $display);
        self::assertStringContainsString('* messenger.transport.doctrine.factory', $display);
    }

    /**
     * The container instantiation suffix is a prefix of the unmanaged factory one, so a factory would be
     * reported in both sections if the suffixes were matched anywhere but at the end of the id.
     */
    public function testAnUnmanagedFactoryIsReportedInItsOwnSectionOnly(): void
    {
        $tester = $this->runCommand(
            serviceIds: ['messenger.transport.doctrine.factory.swoole_coop.wrapped_factory'],
            removedIds: [],
        );

        $display = $tester->getDisplay();

        self::assertStringContainsString('Container instantiation (0)', $display);
        self::assertStringContainsString('Unmanaged factory instantiation (1)', $display);
    }

    /**
     * A proxified service leaves more than one renamed definition behind, and only the one holding the
     * original definition names the service that was proxified. The pool built beside it and the
     * blocking configurator the Doctrine processor wraps are neither of them services the application
     * declared, so counting them would overstate what is pooled.
     */
    public function testIdsWhichAreNotARenamedOriginalDefinitionAreIgnored(): void
    {
        $tester = $this->runCommand(
            serviceIds: [
                'twig.swoole_coop.wrapped',
                'twig.swoole_coop.service_pool',
                'doctrine.orm.default_configuration.swoole_coop.blocking',
                'service_container',
                'router',
            ],
            removedIds: [],
        );

        $display = $tester->getDisplay();

        self::assertStringContainsString('Container instantiation (1)', $display);
        self::assertStringNotContainsString('service_pool', $display);
        self::assertStringNotContainsString('blocking', $display);
        self::assertStringNotContainsString('* router', $display);
    }

    /**
     * Only one of the two proxifiers makes what it renamed public, so half the answer would be missing
     * if the removed ids were not read as well.
     */
    public function testProxifiedServicesAreFoundAmongTheRemovedIdsToo(): void
    {
        $tester = $this->runCommand(
            serviceIds: ['twig.swoole_coop.wrapped'],
            removedIds: ['security.token_storage.swoole_coop.wrapped' => true],
        );

        $display = $tester->getDisplay();

        self::assertStringContainsString('Container instantiation (2)', $display);
        self::assertStringContainsString('* security.token_storage', $display);
    }

    public function testAServiceDeclaredBothAsPresentAndAsRemovedIsReportedOnce(): void
    {
        $tester = $this->runCommand(
            serviceIds: ['twig.swoole_coop.wrapped'],
            removedIds: ['twig.swoole_coop.wrapped' => true],
        );

        self::assertStringContainsString('Container instantiation (1)', $tester->getDisplay());
    }

    public function testServiceIdsAreSorted(): void
    {
        $tester = $this->runCommand(
            serviceIds: [
                'twig.swoole_coop.wrapped',
                'router.swoole_coop.wrapped',
                'cache.app.swoole_coop.wrapped',
            ],
            removedIds: [],
        );

        $display = $tester->getDisplay();
        $positions = [
            mb_strpos($display, '* cache.app'),
            mb_strpos($display, '* router'),
            mb_strpos($display, '* twig'),
        ];

        self::assertNotContains(false, $positions);
        self::assertSame($positions, array_values(array_filter($positions)));
        self::assertTrue($positions[0] < $positions[1] && $positions[1] < $positions[2]);
    }

    public function testTheFilterOptionNarrowsBothSections(): void
    {
        $tester = $this->runCommand(
            serviceIds: [
                'twig.swoole_coop.wrapped',
                'messenger.listener.reset_memory_usage.swoole_coop.wrapped',
                'messenger.transport.doctrine.factory.swoole_coop.wrapped_factory',
            ],
            removedIds: [],
            input: ['--filter' => 'messenger'],
        );

        $display = $tester->getDisplay();

        self::assertStringContainsString('Container instantiation (1)', $display);
        self::assertStringContainsString('* messenger.listener.reset_memory_usage', $display);
        self::assertStringNotContainsString('* twig', $display);
        self::assertStringContainsString('Unmanaged factory instantiation (1)', $display);
    }

    public function testAnEmptySectionSaysSoRatherThanShowingNothing(): void
    {
        $tester = $this->runCommand(serviceIds: ['twig.swoole_coop.wrapped'], removedIds: []);

        $display = $tester->getDisplay();

        self::assertStringContainsString('Unmanaged factory instantiation (0)', $display);
        self::assertStringContainsString('none', $display);
    }

    /**
     * Without coroutine support nothing is proxified, and an empty listing would read as a finding
     * rather than as the answer "this does not apply here".
     */
    public function testCoroutineSupportBeingDisabledIsReportedRatherThanListedAsEmpty(): void
    {
        $tester = $this->runCommand(
            serviceIds: ['twig.swoole_coop.wrapped'],
            removedIds: [],
            coroutinesEnabled: false,
        );

        $display = $tester->getDisplay();

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Coroutine support is disabled', $display);
        self::assertStringNotContainsString('Container instantiation', $display);
    }

    public function testAMissingCoroutinesParameterIsTreatedAsDisabled(): void
    {
        $tester = $this->runCommand(
            serviceIds: ['twig.swoole_coop.wrapped'],
            removedIds: [],
            coroutinesEnabled: null,
        );

        self::assertStringContainsString('Coroutine support is disabled', $tester->getDisplay());
    }

    /**
     * @param array<int, string> $serviceIds
     * @param array<string, bool> $removedIds
     * @param array<string, string> $input
     */
    private function runCommand(
        array $serviceIds,
        array $removedIds,
        ?bool $coroutinesEnabled = true,
        array $input = [],
    ): CommandTester {
        $parameters = $coroutinesEnabled === null
            ? []
            : [ContainerConstants::PARAM_COROUTINES_ENABLED => $coroutinesEnabled];

        $command = new ServicePoolsDebugCommand(
            $this->container(new ParameterBag($parameters), $serviceIds, $removedIds),
        );
        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }

    /**
     * @param array<int, string> $serviceIds
     * @param array<string, bool> $removedIds
     */
    private function container(ParameterBagInterface $parameterBag, array $serviceIds, array $removedIds): Container
    {
        return new class ($parameterBag, $serviceIds, $removedIds) extends Container {
            /**
             * @param array<int, string> $serviceIds
             * @param array<string, bool> $removedIds
             */
            public function __construct(
                ParameterBagInterface $parameterBag,
                private readonly array $serviceIds,
                private readonly array $removedIds,
            ) {
                parent::__construct($parameterBag);
            }

            /**
             * @return array<int, string>
             */
            #[Override]
            public function getServiceIds(): array
            {
                return $this->serviceIds;
            }

            /**
             * @return array<string, bool>
             */
            #[Override]
            public function getRemovedIds(): array
            {
                return $this->removedIds;
            }
        };
    }
}
