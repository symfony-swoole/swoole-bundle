<?php

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Blackfire;

use Blackfire\Client;
use Blackfire\Profile\Configuration;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Upscale\Swoole\Blackfire\Profiler;

class CollectionProfiler extends Profiler
{
    public function __construct(private Client $client)
    {
    }

    /**
     * Starts multiple request profiling when GET parameter "profile_start" is set.
     */
    public function start(Request $request)
    {
        /* @phpstan-ignore-next-line */
        if ($this->probe) {
            return true;
        }

        /* @phpstan-ignore-next-line */
        if (!isset($request->get['profile_start'])) {
            return false;
        }

        // start a probe on each request
        $configuration = new Configuration();
        $title = sprintf('Collection profile: %s', $request->get['profile_start']);
        $configuration->setTitle($title);
        $this->probe = $this->client->createProbe($configuration, false);
        $this->request = $request;

        if (!$this->probe->enable()) {
            $this->reset();

            throw new \UnexpectedValueException('Cannot enable Blackfire profiler');
        }

        return true;
    }

    /**
     * Stops multiple request profiling when GET parameter "profile_stop" is set.
     */
    public function stop(Request $request, Response $response)
    {
        if (!isset($request->get['profile_stop'])) {
            return false;
        }

        /* @phpstan-ignore-next-line */
        if ($this->probe) {
            $this->client->endProbe($this->probe);
            $this->reset();

            return true;
        }

        /* @phpstan-ignore-next-line */
        return false;
    }

    /**
     * Reset profiling session.
     */
    public function reset()
    {
        /* @phpstan-ignore-next-line */
        $this->probe = null;
        /* @phpstan-ignore-next-line */
        $this->request = null;
    }
}
