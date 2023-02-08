<?php

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Blackfire;

use Blackfire\Client;
use Blackfire\Probe;
use Blackfire\Profile\Configuration;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Upscale\Swoole\Blackfire\Profiler;

class CollectionProfiler extends Profiler
{
    private ?Probe $probe = null;

    public function __construct(private Client $client)
    {
    }

    /**
     * Starts multiple request profiling when GET parameter "profile_start" is set.
     */
    public function start(Request $request): bool
    {
        if ($this->probe) {
            return true;
        }

        if (!isset($request->get['profile_start'])) {
            return false;
        }

        // start a probe on each request
        $configuration = new Configuration();
        $title = sprintf('Collection profile: %s', $request->get['profile_start']);
        $configuration->setTitle($title);
        $this->probe = $this->client->createProbe($configuration, false);

        if (!$this->probe->enable()) {
            $this->reset();

            throw new \UnexpectedValueException('Cannot enable Blackfire profiler');
        }

        return true;
    }

    /**
     * Stops multiple request profiling when GET parameter "profile_stop" is set.
     */
    public function stop(Request $request, Response $response): bool
    {
        if (!isset($request->get['profile_stop'])) {
            return false;
        }

        if ($this->probe) {
            $this->client->endProbe($this->probe);
            $this->reset();

            return true;
        }

        return false;
    }

    /**
     * Reset profiling session.
     */
    public function reset()
    {
        $this->probe = null;
    }
}
