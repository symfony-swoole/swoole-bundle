<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\SecureSessionCookiePass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\SecureSessionCookieListener;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class SecureSessionCookiePassTest extends TestCase
{
    public function testTheListenerIsRegisteredWhenCookieSecureIsAuto(): void
    {
        $container = $this->containerWithSessions(['cookie_secure' => 'auto']);

        (new SecureSessionCookiePass())->process($container);

        $this->assertTrue($container->hasDefinition(SecureSessionCookiePass::LISTENER_SERVICE_ID));
        $definition = $container->getDefinition(SecureSessionCookiePass::LISTENER_SERVICE_ID);
        $this->assertSame(SecureSessionCookieListener::class, $definition->getClass());
        $this->assertTrue($definition->hasTag('kernel.event_subscriber'));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function optionsWithoutAuto(): array
    {
        return [
            'always secure' => [['cookie_secure' => true]],
            'never secure' => [['cookie_secure' => false]],
            'not set' => [[]],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('optionsWithoutAuto')]
    public function testTheListenerIsNotRegisteredWhenTheFlagIsNotTheRequestsToDecide(array $options): void
    {
        $container = $this->containerWithSessions($options);

        (new SecureSessionCookiePass())->process($container);

        $this->assertFalse($container->hasDefinition(SecureSessionCookiePass::LISTENER_SERVICE_ID));
    }

    public function testTheListenerIsRegisteredWhenTheSessionListenerIsTheTestOne(): void
    {
        // framework.test makes session_listener an alias of test.session.listener.
        $container = new ContainerBuilder();
        $container->setDefinition('test.session.listener', new Definition());
        $container->setAlias('session_listener', 'test.session.listener');
        $container->setParameter('session.storage.options', ['cookie_secure' => 'auto']);

        (new SecureSessionCookiePass())->process($container);

        $this->assertTrue($container->hasDefinition(SecureSessionCookiePass::LISTENER_SERVICE_ID));
    }

    public function testTheListenerIsNotRegisteredWithoutSessions(): void
    {
        $container = new ContainerBuilder();

        (new SecureSessionCookiePass())->process($container);

        $this->assertFalse($container->hasDefinition(SecureSessionCookiePass::LISTENER_SERVICE_ID));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function containerWithSessions(array $options): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('session_listener', new Definition());
        $container->setParameter('session.storage.options', $options);

        return $container;
    }
}
