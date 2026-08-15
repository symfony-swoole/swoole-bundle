<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Bridge\Symfony\WebProfiler;

use Assert\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\WebProfiler\ContentSecurityPolicyHandlerResetter;
use Symfony\Bundle\WebProfilerBundle\Csp\ContentSecurityPolicyHandler;
use Symfony\Bundle\WebProfilerBundle\Csp\NonceGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ContentSecurityPolicyHandlerResetterTest extends TestCase
{
    public function testResetTurnsTheContentSecurityPolicyBackOn(): void
    {
        $handler = new ContentSecurityPolicyHandler(new NonceGenerator());
        $handler->disableCsp();

        (new ContentSecurityPolicyHandlerResetter())->reset($handler);

        self::assertFalse(self::cspDisabledOf($handler));
    }

    /**
     * The behaviour the flag actually controls: a disabled handler strips the headers instead of
     * writing them, and that is what a request served after the reset must not inherit.
     */
    public function testAResetHandlerWritesCspHeadersAgain(): void
    {
        $handler = new ContentSecurityPolicyHandler(new NonceGenerator());
        $handler->disableCsp();

        self::assertSame([], $handler->updateResponseHeaders(new Request(), new Response()));

        (new ContentSecurityPolicyHandlerResetter())->reset($handler);

        self::assertNotSame([], $handler->updateResponseHeaders(new Request(), new Response()));
    }

    public function testResetOfAnAlreadyEnabledHandlerLeavesItEnabled(): void
    {
        $handler = new ContentSecurityPolicyHandler(new NonceGenerator());

        (new ContentSecurityPolicyHandlerResetter())->reset($handler);

        self::assertFalse(self::cspDisabledOf($handler));
    }

    public function testResetRejectsAnUnsupportedObject(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ContentSecurityPolicyHandlerResetter())->reset(new stdClass());
    }

    public function testResetReusesTheSameReflectionPropertyInstanceAcrossCalls(): void
    {
        $resetter = new ContentSecurityPolicyHandlerResetter();
        $reflectionProperty = new ReflectionProperty(
            ContentSecurityPolicyHandlerResetter::class,
            'cspDisabledProperty',
        );

        self::assertNull($reflectionProperty->getValue($resetter));

        $resetter->reset(new ContentSecurityPolicyHandler(new NonceGenerator()));
        $cachedInstance = $reflectionProperty->getValue($resetter);

        self::assertNotNull($cachedInstance);

        $resetter->reset(new ContentSecurityPolicyHandler(new NonceGenerator()));

        self::assertSame($cachedInstance, $reflectionProperty->getValue($resetter));
    }

    private static function cspDisabledOf(ContentSecurityPolicyHandler $handler): bool
    {
        return (bool) (new ReflectionProperty(ContentSecurityPolicyHandler::class, 'cspDisabled'))
            ->getValue($handler);
    }
}
