<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as RouteAnnotation;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final class TwigController
{
    public function __construct(
        private readonly Environment $environment,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @RouteAnnotation("/twig")
     * @throws InvalidArgumentException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    #[Route(path: '/twig')]
    public function indexAction(): Response
    {
        $this->logger->error('Profiler logging test.');

        return new Response($this->environment->render('base.html.twig'));
    }
}
