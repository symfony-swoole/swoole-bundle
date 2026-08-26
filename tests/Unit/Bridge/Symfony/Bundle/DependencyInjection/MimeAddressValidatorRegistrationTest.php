<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\SwooleExtension;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Mime\MimeAddressValidatorInstaller;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The wiring that makes the validator Address ends up holding a pooled one.
 *
 * All of it is the tag: StatefulServicesPass is what turns a tagged service into a pool and a proxy, so
 * what has to be true here is that the service exists, that it is the class Address's property will
 * accept, and that it carries the tag. What the pass then does with it is its own tests' business.
 */
#[CoversClass(SwooleExtension::class)]
final class MimeAddressValidatorRegistrationTest extends TestCase
{
    private const string VALIDATOR_ID = 'swoole_bundle.mime.email_validator';

    public function testTheValidatorIsRegisteredAsAStatefulService(): void
    {
        $container = $this->load(coroutinesEnabled: true);

        self::assertTrue($container->hasDefinition(self::VALIDATOR_ID));
        self::assertNotSame(
            [],
            $container->getDefinition(self::VALIDATOR_ID)->getTag(ContainerConstants::TAG_STATEFUL_SERVICE),
        );
    }

    /**
     * The property is typed EmailValidator, so anything else here would fail on assignment in a booting
     * server rather than in a test.
     */
    public function testTheRegisteredValidatorIsWhatAddressWillAccept(): void
    {
        $container = $this->load(coroutinesEnabled: true);

        self::assertSame(
            'Egulias\EmailValidator\EmailValidator',
            $container->getDefinition(self::VALIDATOR_ID)->getClass(),
        );
    }

    public function testTheInstallerIsBootedWithIt(): void
    {
        $container = $this->load(coroutinesEnabled: true);
        $installer = $container->getDefinition(MimeAddressValidatorInstaller::class);

        self::assertNotSame([], $installer->getTag('swoole_bundle.bootable_service'));
        self::assertSame(self::VALIDATOR_ID, (string) $installer->getArgument('$validator'));
    }

    /**
     * A process doing one thing at a time shares the stock validator perfectly happily, and pooling
     * needs a pass that does not run at all when coroutines are off.
     */
    public function testNothingIsRegisteredWithoutCoroutines(): void
    {
        $container = $this->load(coroutinesEnabled: false);

        self::assertFalse($container->hasDefinition(self::VALIDATOR_ID));
        self::assertFalse($container->hasDefinition(MimeAddressValidatorInstaller::class));
    }

    private function load(bool $coroutinesEnabled): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.logs_dir', sys_get_temp_dir());
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');

        (new SwooleExtension())->load([[
            'platform' => ['coroutines' => ['enabled' => $coroutinesEnabled]],
        ]], $container);

        return $container;
    }
}
