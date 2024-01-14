<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Configurator;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Configurator\WithRequestHandler;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\RequestHandler\RequestHandlerDummy;
use SwooleBundle\SwooleBundle\Tests\Unit\Server\SwooleHttpServerMockFactory;

/**
 * @runTestsInSeparateProcesses
 */
class WithRequestHandlerTest extends TestCase
{
    /**
     * @var RequestHandlerDummy
     */
    private $requestHandlerDummy;

    /**
     * @var WithRequestHandler
     */
    private $configurator;

    protected function setUp(): void
    {
        $this->requestHandlerDummy = new RequestHandlerDummy();

        $this->configurator = new WithRequestHandler($this->requestHandlerDummy);
    }

    public function testConfigure(): void
    {
        $serverMock = SwooleHttpServerMockFactory::make();

        $this->configurator->configure($serverMock);

        self::assertTrue($serverMock->registeredEvent);
        self::assertSame(['request', [$this->requestHandlerDummy, 'handle']], $serverMock->registeredEventPair);
    }
}
