<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use Doctrine\DBAL\Connection;
use Exception;
use ReflectionClass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\BaseServicePool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\DefaultDummyService;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\DummyService;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\NonSharedExample;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\ShouldBeProxified;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\ShouldBeProxified2;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\SleepingCounter;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service\SleepingCounterChecker;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as RouteAnnotation;
use Symfony\Component\Routing\Attribute\Route;

final class SleepController
{
    /** @phpstan-ignore-next-line */
    private array $nonShared = [];

    public function __construct(
        private readonly SleepingCounter $sleepingCounter,
        private readonly SleepingCounterChecker $checker,
        private readonly ShouldBeProxified $shouldBeProxified,
        private readonly ShouldBeProxified2 $shouldBeProxified2,
        private readonly DummyService $dummyService,
        private readonly Connection $connection,
        private readonly ContainerInterface $container,
        private readonly ServicePoolContainer $servicePoolContainer,
    ) {}

    /**
     * @RouteAnnotation(
     *     methods={"GET"},
     *     path="/sleep"
     * )
     * @throws Exception
     */
    #[Route(path: '/sleep', methods: ['GET'])]
    public function index(): Response
    {
        $firstCount = $this->servicePoolContainer->count();
        $this->nonShared[] = $this->container->get(NonSharedExample::class);
        $poolWasAdded = $this->servicePoolContainer->count() > $firstCount;

        $this->sleepingCounter->sleepAndCount();
        $counter = $this->sleepingCounter->getCounter();
        $check = $this->checker->wasChecked() ? 'true' : 'false';
        $checks = $this->checker->getChecks();
        /** @phpstan-ignore-next-line */
        $isProxified = $this->shouldBeProxified instanceof ContextualProxy ? 'was' : 'WAS NOT';
        /** @phpstan-ignore-next-line */
        $isProxified2 = $this->shouldBeProxified2 instanceof ContextualProxy ? 'was' : 'WAS NOT';
        /** @phpstan-ignore-next-line */
        $servicePool = $this->shouldBeProxified2->getServicePool();
        $rc = new ReflectionClass(BaseServicePool::class);
        $limitProperty = $rc->getProperty('instancesLimit');
        $limitProperty->setAccessible(true);
        $limit = $limitProperty->getValue($servicePool);
        $alwaysResetWorks = $this->shouldBeProxified->wasDummyReset();
        $alwaysResetSafe = $this->shouldBeProxified->getSafeDummy();
        /** @phpstan-ignore-next-line */
        $safeDummyIsProxy = $alwaysResetSafe instanceof ContextualProxy ? 'IS' : 'is not';
        $safeAlwaysResetWorks = $alwaysResetSafe->getWasReset();

        $rc2 = new ReflectionClass(DefaultDummyService::class);
        $tmpRepoProperty = $rc2->getProperty('tmpRepository');
        $tmpRepoProperty->setAccessible(true);
        /** @phpstan-ignore-next-line */
        $realDummyService = $this->dummyService->getDecorated();
        $tmpRepo = $realDummyService->getTmpRepository();
        $isProxified3 = $tmpRepo instanceof ContextualProxy ? 'was' : 'WAS NOT';
        $connServicePool = $tmpRepo->getServicePool();
        $limit2 = $limitProperty->getValue($connServicePool);

        /** @phpstan-ignore-next-line */
        $connServicePool = $this->connection->getServicePool();
        $connlimit = $limitProperty->getValue($connServicePool);

        return new Response(
            "<html><body>Sleep was fine. Count was {$counter}. Check was {$check}. "
                    . "Checks: {$checks}. "
                    . "Service {$isProxified} proxified. Service2 {$isProxified2} proxified. "
                    . 'Always reset ' . ($alwaysResetWorks ? 'works' : 'did not work') . '. '
                    . "Safe always reseter {$safeDummyIsProxy} a proxy. "
                    . 'Safe Always reset ' . ($safeAlwaysResetWorks ? 'works' : 'did not work') . '. '
                    . "Service2 limit is {$limit}. TmpRepo {$isProxified3} proxified. "
                    . "TmpRepo limit is {$limit2}. "
                    . "Connection limit is {$connlimit}.</body></html> "
                    . 'Service pool for NonShared was ' . ($poolWasAdded ? '' : 'NOT ') . 'added.'
        );
    }
}
