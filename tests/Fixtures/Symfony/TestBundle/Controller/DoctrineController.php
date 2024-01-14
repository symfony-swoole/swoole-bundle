<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Resetter\CountingResetter;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\AdvancedDoctrineUsage;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\DummyService;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\NoAutowiring\ResetCountingRegistry;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as RouteAnnotation;
use Symfony\Component\Routing\Attribute\Route;

final class DoctrineController
{
    /**
     * @param array<string, CountingResetter> $resetters
     */
    public function __construct(
        private readonly DummyService $dummyService,
        private readonly AdvancedDoctrineUsage $advancedUsage,
        private readonly ResetCountingRegistry $registry,
        private readonly array $resetters = [],
        private readonly ?DebugDataHolder $dataHolder = null
    ) {
    }

    /**
     * @RouteAnnotation(
     *     methods={"GET"},
     *     path="/doctrine"
     * )
     */
    #[Route(path: '/doctrine', methods: ['GET'])]
    public function index(): Response
    {
        $tests = $this->dummyService->process();

        $testsStr = '';

        foreach ($tests as $test) {
            $testsStr .= $test->getUuid().'<br>';
        }

        return new Response(
            '<html><body>'.$testsStr.'</body></html>'
        );
    }

    /**
     * @RouteAnnotation(
     *     methods={"GET"},
     *     path="/doctrine-advanced"
     * )
     */
    #[Route(path: '/doctrine-advanced', methods: ['GET'])]
    public function advancedUsage(): JsonResponse
    {
        $incr = $this->advancedUsage->run();

        return new JsonResponse([
            'increment' => $incr,
            'resets' => $this->registry->getResetCount(),
            'doctrineClass' => $this->registry::class,
        ]);
    }

    /**
     * @RouteAnnotation(
     *     methods={"GET"},
     *     path="/doctrine-queries"
     * )
     */
    #[Route(path: '/doctrine-queries', methods: ['GET'])]
    public function queries(): JsonResponse
    {
        return new JsonResponse($this->dataHolder->getData()['default']);
    }

    /**
     * @RouteAnnotation(
     *     methods={"GET"},
     *     path="/doctrine-resets"
     * )
     */
    #[Route(path: '/doctrine-resets', methods: ['GET'])]
    public function pings(): JsonResponse
    {
        $data = array_map(fn (CountingResetter $resetter): int => $resetter->getCounter(), $this->resetters);

        return new JsonResponse($data);
    }
}
