<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Upscale\Newrelic;

use K911\Swoole\Server\Configurator\ConfiguratorInterface;
use Swoole\Http\Server;
use Upscale\Swoole\Newrelic;

final class WithApm implements ConfiguratorInterface
{
//    /**
//     * @var Profiler
//     */
//    private $profiler;
//
//    public function __construct(Profiler $profiler)
//    {
//        $this->profiler = $profiler;
//    }

    /**
     * {@inheritdoc}
     */
    public function configure(Server $server): void
    {
        // Real user monitoring (RUM)
        $rum = new Newrelic\Browser(new Newrelic\Browser\TransactionFactory());
        $rum->instrument($server);

        // Application performnce monitoring (APM)
        $apm = new Newrelic\Apm(new Newrelic\Apm\TransactionFactory());
        $apm->instrument($server);
    }
}
