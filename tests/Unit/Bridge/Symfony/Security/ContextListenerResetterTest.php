<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\Security;

use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Security\ContextListenerResetter;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Http\Firewall\ContextListener;

final class ContextListenerResetterTest extends TestCase
{
    /**
     * A listener that thinks it is still registered never registers again, and once that happens nothing
     * writes the security token back to the session for any request the instance goes on to serve.
     */
    public function testAListenerLeftThinkingItIsRegisteredIsPutBack(): void
    {
        $listener = $this->contextListener();
        $registered = new ReflectionProperty(ContextListener::class, 'registered');
        $registered->setValue($listener, true);

        (new ContextListenerResetter())->reset($listener);

        self::assertFalse($registered->getValue($listener));
    }

    public function testResettingAListenerThatIsAlreadyBackToNormalChangesNothing(): void
    {
        $listener = $this->contextListener();
        $registered = new ReflectionProperty(ContextListener::class, 'registered');

        (new ContextListenerResetter())->reset($listener);

        self::assertFalse($registered->getValue($listener));
    }

    public function testResettingSomethingElseIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ContextListenerResetter())->reset(new stdClass());
    }

    public function testTheSameReflectionPropertyInstanceIsReusedAcrossCalls(): void
    {
        $resetter = new ContextListenerResetter();
        $cache = new ReflectionProperty(ContextListenerResetter::class, 'registeredProperty');

        self::assertNull($cache->getValue($resetter));

        $resetter->reset($this->contextListener());
        $cachedInstance = $cache->getValue($resetter);

        self::assertNotNull($cachedInstance);

        $resetter->reset($this->contextListener());

        self::assertSame($cachedInstance, $cache->getValue($resetter));
    }

    private function contextListener(): ContextListener
    {
        return new ContextListener(new TokenStorage(), [], 'main');
    }
}
