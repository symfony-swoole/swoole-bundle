<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Command;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\DebugContainerRedumpPass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\{
    Proxifier,
    UnmanagedFactoryProxifier,
};
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServicesPass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\ContainerConstants;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\UnmanagedFactoryInstantiator;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\DiServicePool;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\ServicePool\UnmanagedFactoryServicePool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Container;

/**
 * Lists the services StatefulServicesPass has replaced with a pooled proxy.
 *
 * Read off the compiled container the application is running, which is what lets this answer in any
 * environment and without rebuilding anything - including in production, where `debug:container` has
 * no dump to read at all. What it reports are the ids each proxifier leaves the original definition
 * under once it has put a proxy in its place.
 *
 * `debug:container` covers the same ground in a debug kernel, in more detail and one service at a
 * time, but only because DebugContainerRedumpPass rewrites the dump it reads after this pass has
 * run. The digest below is the view that one cannot give.
 *
 * The renamed definitions are looked for among the container's service ids and its removed ids
 * together, because only one of the two sections is public. Proxifier makes the definition it wraps
 * public - the pool fetches it by id at runtime - while UnmanagedFactoryProxifier leaves the factory's
 * own visibility alone, so a private factory ends up among the ids the dumped container remembers by
 * name but no longer answers to.
 *
 * @see StatefulServicesPass
 * @see DebugContainerRedumpPass
 */
final class ServicePoolsDebugCommand extends Command
{
    /**
     * @see Proxifier::doProxifyService()
     */
    private const string CONTAINER_INSTANTIATION_SUFFIX = '.swoole_coop.wrapped';

    /**
     * @see UnmanagedFactoryProxifier::proxifyService()
     */
    private const string UNMANAGED_FACTORY_SUFFIX = '.swoole_coop.wrapped_factory';

    public function __construct(private readonly Container $container)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('List the services proxified into coroutine service pools.');
        $this->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Show only ids containing this string.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Swoole service pools');

        if (!$this->areCoroutinesEnabled()) {
            $io->warning(sprintf(
                'Coroutine support is disabled (%s), so no service has been proxified.',
                ContainerConstants::PARAM_COROUTINES_ENABLED,
            ));

            return self::SUCCESS;
        }

        /** @var string|null $filter */
        $filter = $input->getOption('filter');
        $ids = $this->declaredServiceIds();

        $this->renderSection(
            $io,
            'Container instantiation',
            sprintf(
                'One instance per coroutine, built by the container and handed out by %s.',
                $this->shortName(DiServicePool::class),
            ),
            $this->proxifiedServiceIds($ids, self::CONTAINER_INSTANTIATION_SUFFIX, $filter),
        );

        $this->renderSection(
            $io,
            'Unmanaged factory instantiation',
            sprintf(
                'One instance per coroutine of whatever the factory below builds, handed out by a %s '
                . 'per factory method, registered on the first call through %s.',
                $this->shortName(UnmanagedFactoryServicePool::class),
                $this->shortName(UnmanagedFactoryInstantiator::class),
            ),
            $this->proxifiedServiceIds($ids, self::UNMANAGED_FACTORY_SUFFIX, $filter),
        );

        return self::SUCCESS;
    }

    private function areCoroutinesEnabled(): bool
    {
        if (!$this->container->hasParameter(ContainerConstants::PARAM_COROUTINES_ENABLED)) {
            return false;
        }

        return (bool) $this->container->getParameter(ContainerConstants::PARAM_COROUTINES_ENABLED);
    }

    /**
     * Every id the compiled container knows a name for, whether or not it still answers to `get()`.
     *
     * @return array<int, string>
     */
    private function declaredServiceIds(): array
    {
        return array_values(array_unique(array_merge(
            $this->container->getServiceIds(),
            array_map(strval(...), array_keys($this->container->getRemovedIds())),
        )));
    }

    /**
     * The proxified service ids, recovered by stripping the suffix the proxifier renamed them with.
     *
     * The two suffixes cannot be confused with one another: an id ending in the unmanaged factory one
     * does not also end in the container instantiation one.
     *
     * @param array<int, string> $ids
     * @return array<int, string>
     */
    private function proxifiedServiceIds(array $ids, string $suffix, ?string $filter): array
    {
        $proxified = [];

        foreach ($ids as $id) {
            if (!str_ends_with($id, $suffix)) {
                continue;
            }

            $serviceId = mb_substr($id, 0, -mb_strlen($suffix));

            if ($filter !== null && !str_contains($serviceId, $filter)) {
                continue;
            }

            $proxified[] = $serviceId;
        }

        sort($proxified);

        return $proxified;
    }

    /**
     * @param array<int, string> $serviceIds
     */
    private function renderSection(SymfonyStyle $io, string $title, string $description, array $serviceIds): void
    {
        $io->section(sprintf('%s (%d)', $title, count($serviceIds)));
        $io->text($description);
        $io->newLine();

        if ($serviceIds === []) {
            $io->text('none');
            $io->newLine();

            return;
        }

        $io->listing($serviceIds);
    }

    /**
     * @param class-string $class
     */
    private function shortName(string $class): string
    {
        $position = mb_strrpos($class, '\\');

        return $position === false ? $class : mb_substr($class, $position + 1);
    }
}
