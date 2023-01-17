<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Controller;

use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Resetter\CountingResetter;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\AdvancedDoctrineUsage;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\DummyService;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\NoAutowiring\ResetCountingRegistry;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class DoctrineController
{
    private DummyService $dummyService;

    private AdvancedDoctrineUsage $advancedUsage;

    private ResetCountingRegistry $registry;

    /**
     * @var array<string, CountingResetter>
     */
    private array $resetters;

    private ?DebugDataHolder $dataHolder;

    public function __construct(
        DummyService $dummyService,
        AdvancedDoctrineUsage $advancedUsage,
        ResetCountingRegistry $registry,
        array $resetters = [],
        ?DebugDataHolder $dataHolder = null
    ) {
        $this->dummyService = $dummyService;
        $this->advancedUsage = $advancedUsage;
        $this->registry = $registry;
        $this->resetters = $resetters;
        $this->dataHolder = $dataHolder;
    }

    /**
     * @Route(
     *     methods={"GET"},
     *     path="/doctrine"
     * )
     */
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
     * @Route(
     *     methods={"GET"},
     *     path="/doctrine-advanced"
     * )
     */
    public function advancedUsage(): JsonResponse
    {
        $incr = $this->advancedUsage->run();

        return new JsonResponse([
            'increment' => $incr,
            'resets' => $this->registry->getResetCount(),
            'doctrineClass' => get_class($this->registry),
        ]);
    }

    /**
     * @Route(
     *     methods={"GET"},
     *     path="/doctrine-queries"
     * )
     */
    public function queries(): JsonResponse
    {
        return new JsonResponse($this->dataHolder->getData()['default']);
    }

    /**
     * @Route(
     *     methods={"GET"},
     *     path="/doctrine-resets"
     * )
     */
    public function pings(): JsonResponse
    {
        $data = array_map(fn (CountingResetter $resetter): int => $resetter->getCounter(), $this->resetters);

        return new JsonResponse($data);
    }
}
