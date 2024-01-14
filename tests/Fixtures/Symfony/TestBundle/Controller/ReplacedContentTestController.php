<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as RouteAnnotation;
use Symfony\Component\Routing\Attribute\Route;

final class ReplacedContentTestController
{
    private const BASH_REPLACE_PATTERN = 'Wrong response!';

    /**
     * @RouteAnnotation(
     *     methods={"GET"},
     *     path="/test/replaced/content"
     * )
     */
    #[Route(path: '/test/replaced/content', methods: ['GET'])]
    public function index(): Response
    {
        return new Response(self::BASH_REPLACE_PATTERN, 200, ['Content-Type' => 'text/plain']);
    }
}
