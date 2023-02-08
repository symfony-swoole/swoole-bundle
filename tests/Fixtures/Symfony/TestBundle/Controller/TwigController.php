<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class TwigController
{
    public function __construct(
        private Environment $environment,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @Route("/twig")
     *
     * @throws \InvalidArgumentException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function indexAction(): Response
    {
        $this->logger->error('Profiler logging test.');

        return new Response($this->environment->render('base.html.twig'));
    }
}
