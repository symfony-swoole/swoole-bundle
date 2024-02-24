<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use DateTimeImmutable;
use Exception;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message\CreateFileMessage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

final class TaskController
{
    /**
     * @throws Exception
     */
    #[Route(path: '/message/dispatch', methods: ['GET', 'POST'])]
    public function dispatchMessage(MessageBusInterface $bus, Request $request): Response
    {
        $fileName = $request->get('fileName', 'test-default-file.txt');
        $content = $request->get('content', (new DateTimeImmutable())->format(DATE_ATOM));
        $message = new CreateFileMessage($fileName, $content);
        $bus->dispatch($message);

        return new Response('OK', 200, ['Content-Type' => 'text/plain']);
    }
}
