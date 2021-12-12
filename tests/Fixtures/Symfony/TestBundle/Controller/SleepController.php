<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Controller;

use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\ShouldBeProxified;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\SleepingCounter;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\SleepingCounterChecker;
use ProxyManager\Proxy\VirtualProxyInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class SleepController
{
    private SleepingCounter $sleepingCounter;

    private SleepingCounterChecker $checker;

    private ShouldBeProxified $shouldBeProxified;

    public function __construct(
        SleepingCounter $sleepingCounter,
        SleepingCounterChecker $checker,
        ShouldBeProxified $shouldBeProxified
    ) {
        $this->sleepingCounter = $sleepingCounter;
        $this->checker = $checker;
        $this->shouldBeProxified = $shouldBeProxified;
    }

    /**
     * @Route(
     *     methods={"GET"},
     *     path="/sleep"
     * )
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function index()
    {
        $this->sleepingCounter->sleepAndCount();
        $counter = $this->sleepingCounter->getCounter();
        $check = $this->checker->wasChecked() ? 'true' : 'false';
        /** @phpstan-ignore-next-line */
        $isProxified = $this->shouldBeProxified instanceof VirtualProxyInterface ? 'was' : 'WAS NOT';

        return new Response(
            "<html><body>Sleep was fine. Count was {$counter}. Check was {$check}. "
                    ."Service {$isProxified} proxified.</body></html>"
        );
    }
}
