<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Controller;

use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Message\RunDummy;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Message\SleepAndAppend;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

final class CoroutinesTaskController
{
    /**
     * @Route(
     *     methods={"GET","POST"},
     *     path="/coroutines/message/sleep-and-append"
     * )
     *
     * @throws \Exception
     */
    public function dispatchSleepAndAppendMessage(MessageBusInterface $bus, Request $request): Response
    {
        $fileName = $request->get('fileName', 'test-default-file.txt');
        $sleep = (int) $request->get('sleep', 500);
        $append = $request->get('append', '__DEFAULT__');
        $message = new SleepAndAppend($fileName, $sleep, $append);
        $bus->dispatch($message);

        return new Response('OK', 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * @Route(
     *     methods={"GET","POST"},
     *     path="/coroutines/message/run-dummy"
     * )
     *
     * @throws \Exception
     */
    public function dispatchRunDummyMessage(MessageBusInterface $bus): Response
    {
        $message = new RunDummy();
        $bus->dispatch($message);

        return new Response('OK', 200, ['Content-Type' => 'text/plain']);
    }
}
