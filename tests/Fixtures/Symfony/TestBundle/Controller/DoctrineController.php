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
use Symfony\Component\Routing\Attribute\Route;

final readonly class DoctrineController
{
    /**
     * @param array<string, CountingResetter> $resetters
     */
    public function __construct(
        private DummyService $dummyService,
        private AdvancedDoctrineUsage $advancedUsage,
        private ResetCountingRegistry $registry,
        private array $resetters = [],
        private ?DebugDataHolder $dataHolder = null,
    ) {}

    #[Route(path: '/doctrine', methods: ['GET'])]
    public function index(): Response
    {
        $tests = $this->dummyService->process();

        $testsStr = '';

        foreach ($tests as $test) {
            $testsStr .= $test->getUuid() . '<br>';
        }

        return new Response('<html><body>' . $testsStr . '</body></html>');
    }

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

    #[Route(path: '/doctrine-queries', methods: ['GET'])]
    public function queries(): JsonResponse
    {
        return new JsonResponse($this->dataHolder->getData()['default']);
    }

    #[Route(path: '/doctrine-resets', methods: ['GET'])]
    public function pings(): JsonResponse
    {
        $data = array_map(static fn(CountingResetter $resetter): int => $resetter->getCounter(), $this->resetters);

        return new JsonResponse($data);
    }
}
