<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use Override;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The one fixture controller extending AbstractController, counting how often its container is written.
 *
 * Every write to AbstractController::$container goes through setContainer(), including the one performed
 * when the service is built, so the counter is the whole story: it should read 1 for the life of the
 * worker, however many requests this controller goes on to serve.
 */
final class AbstractBasedController extends AbstractController
{
    private int $containerWrites = 0;

    #[Route(path: '/abstract-based-controller', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        // json() reaches for the container behind AbstractController, so a response getting here at all
        // proves it is not merely unwritten but still set and usable
        return $this->json(['containerWrites' => $this->containerWrites]);
    }

    #[Override]
    public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        $this->containerWrites++;

        return parent::setContainer($container);
    }

    public function containerWrites(): int
    {
        return $this->containerWrites;
    }
}
