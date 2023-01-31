<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Controller;

use Doctrine\DBAL\Connection;
use K911\Swoole\Bridge\Symfony\Container\ServicePool\BaseServicePool;
use K911\Swoole\Bridge\Symfony\Container\ServicePool\ServicePoolContainer;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\DefaultDummyService;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\DummyService;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\NonSharedExample;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\ShouldBeProxified;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\ShouldBeProxified2;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\SleepingCounter;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\SleepingCounterChecker;
use ProxyManager\Proxy\VirtualProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class SleepController
{
    private SleepingCounter $sleepingCounter;

    private SleepingCounterChecker $checker;

    private ShouldBeProxified $shouldBeProxified;

    private ShouldBeProxified2 $shouldBeProxified2;

    private DummyService $dummyService;

    private Connection $connection;

    private ContainerInterface $container;

    private ServicePoolContainer $servicePoolContainer;

    /** @phpstan-ignore-next-line */
    private array $nonShared = [];

    public function __construct(
        SleepingCounter $sleepingCounter,
        SleepingCounterChecker $checker,
        ShouldBeProxified $shouldBeProxified,
        ShouldBeProxified2 $shouldBeProxified2,
        DummyService $dummyService,
        Connection $connection,
        ContainerInterface $container,
        ServicePoolContainer $servicePoolContainer
    ) {
        $this->sleepingCounter = $sleepingCounter;
        $this->checker = $checker;
        $this->shouldBeProxified = $shouldBeProxified;
        $this->shouldBeProxified2 = $shouldBeProxified2;
        $this->dummyService = $dummyService;
        $this->connection = $connection;
        $this->container = $container;
        $this->servicePoolContainer = $servicePoolContainer;
    }

    /**
     * @Route(
     *     methods={"GET"},
     *     path="/sleep"
     * )
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function index()
    {
        $firstCount = $this->servicePoolContainer->count();
        $this->nonShared[] = $this->container->get(NonSharedExample::class);
        $poolWasAdded = $this->servicePoolContainer->count() > $firstCount;

        $this->sleepingCounter->sleepAndCount();
        $counter = $this->sleepingCounter->getCounter();
        $check = $this->checker->wasChecked() ? 'true' : 'false';
        $checks = $this->checker->getChecks();
        /** @phpstan-ignore-next-line */
        $isProxified = $this->shouldBeProxified instanceof VirtualProxyInterface ? 'was' : 'WAS NOT';
        /** @phpstan-ignore-next-line */
        $isProxified2 = $this->shouldBeProxified2 instanceof VirtualProxyInterface ? 'was' : 'WAS NOT';
        /** @phpstan-ignore-next-line */
        $initializer = $this->shouldBeProxified2->getProxyInitializer();
        $rf = new \ReflectionFunction($initializer);
        $servicePool = $rf->getStaticVariables()['servicePool'];
        $rc = new \ReflectionClass(BaseServicePool::class);
        $limitProperty = $rc->getProperty('instancesLimit');
        $limitProperty->setAccessible(true);
        $limit = $limitProperty->getValue($servicePool);
        $alwaysResetWorks = $this->shouldBeProxified->wasDummyReset();
        $alwaysResetSafe = $this->shouldBeProxified->getSafeDummy();
        /** @phpstan-ignore-next-line */
        $safeDummyIsProxy = $alwaysResetSafe instanceof VirtualProxyInterface ? 'IS' : 'is not';
        $safeAlwaysResetWorks = $alwaysResetSafe->getWasReset();

        $rc2 = new \ReflectionClass(DefaultDummyService::class);
        $tmpRepoProperty = $rc2->getProperty('tmpRepository');
        $tmpRepoProperty->setAccessible(true);
        /** @phpstan-ignore-next-line */
        $realDummyService = $this->dummyService->getDecorated();
        $tmpRepo = $realDummyService->getTmpRepository();
        $isProxified3 = $tmpRepo instanceof VirtualProxyInterface ? 'was' : 'WAS NOT';
        $initializer2 = $tmpRepo->getProxyInitializer();
        $rf2 = new \ReflectionFunction($initializer2);
        $connServicePool = $rf2->getStaticVariables()['servicePool'];
        $limit2 = $limitProperty->getValue($connServicePool);

        /** @phpstan-ignore-next-line */
        $connInitializer = $this->connection->getProxyInitializer();
        $rf3 = new \ReflectionFunction($connInitializer);
        $connServicePool = $rf3->getStaticVariables()['servicePool'];
        $connlimit = $limitProperty->getValue($connServicePool);

        return new Response(
            "<html><body>Sleep was fine. Count was {$counter}. Check was {$check}. "
                    ."Checks: {$checks}. "
                    ."Service {$isProxified} proxified. Service2 {$isProxified2} proxified. "
                    .'Always reset '.($alwaysResetWorks ? 'works' : 'did not work').'. '
                    ."Safe always reseter {$safeDummyIsProxy} a proxy. "
                    .'Safe Always reset '.($safeAlwaysResetWorks ? 'works' : 'did not work').'. '
                    ."Service2 limit is {$limit}. TmpRepo {$isProxified3} proxified. "
                    ."TmpRepo limit is {$limit2}. "
                    ."Connection limit is {$connlimit}.</body></html> "
                    .'Service pool for NonShared was '.($poolWasAdded ? '' : 'NOT ').'added.'
        );
    }
}
