<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\DataCollector\LeakyDataCollector;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\LeakyResource;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exposes the fixtures used to prove that a pooled service which implements ResetInterface really is
 * reset between requests, no matter how it was registered.
 *
 * Each route records one entry into the current request's instance and reports how many entries were
 * already there *before* this request added its own - i.e. whatever survived from an earlier request
 * served by the same recycled instance. If reset() runs between requests that number is always 0; if
 * it does not, it grows by one per request, forever.
 *
 * @see \SwooleBundle\SwooleBundle\Tests\Feature\PooledServiceResetTest
 */
final readonly class LeakyServicesController
{
    public function __construct(
        private LeakyResource $statefulOnlyResource,
        private LeakyResource $kernelResetResource,
        private LeakyResource $resetOnEachRequestResource,
        private LeakyDataCollector $dataCollector,
        private LeakyDataCollector $resetOnEachRequestDataCollector,
    ) {}

    #[Route(path: '/leaky-resource/stateful-only', methods: ['GET'])]
    public function statefulOnlyResource(): JsonResponse
    {
        return $this->recordAndReport($this->statefulOnlyResource);
    }

    #[Route(path: '/leaky-resource/kernel-reset', methods: ['GET'])]
    public function kernelResetResource(): JsonResponse
    {
        return $this->recordAndReport($this->kernelResetResource);
    }

    #[Route(path: '/leaky-resource/reset-on-each-request', methods: ['GET'])]
    public function resetOnEachRequestResource(): JsonResponse
    {
        return $this->recordAndReport($this->resetOnEachRequestResource);
    }

    #[Route(path: '/leaky-collector/plain', methods: ['GET'])]
    public function dataCollector(): JsonResponse
    {
        return $this->recordAndReportCollector($this->dataCollector);
    }

    #[Route(path: '/leaky-collector/reset-on-each-request', methods: ['GET'])]
    public function resetOnEachRequestDataCollector(): JsonResponse
    {
        return $this->recordAndReportCollector($this->resetOnEachRequestDataCollector);
    }

    private function recordAndReport(LeakyResource $resource): JsonResponse
    {
        $countBefore = count($resource->getEntries());
        $resource->record('x');

        return new JsonResponse(['count_before_this_request' => $countBefore]);
    }

    private function recordAndReportCollector(LeakyDataCollector $collector): JsonResponse
    {
        $countBefore = $collector->getCount();
        $collector->record('x');

        return new JsonResponse(['count_before_this_request' => $countBefore]);
    }
}
