<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;
use Symfony\Bridge\Twig\Extension\ProfilerExtension;
use Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/**
 * Reports whether the Twig extensions counting where a render is are one per coroutine.
 *
 * Both keep a stack: `twig.extension.profiler` the open profiles and their stopwatch events,
 * `twig.extension.webprofiler` a depth counter and the memory stream profiler_dump() writes into. Every
 * template, block and macro entered and left writes to them, so one shared instance behind concurrent
 * renders means two requests pushing and popping the same stack.
 *
 * The Environment is pooled, so an extension belonging to its Environment is already an extension per
 * coroutine - which is what this asks: whether two coroutines rendering at once would be counting on
 * the same object.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Twig\TwigProcessor
 */
#[AsCommand(
    name: 'test:twig-profiler-extensions:sharing-check',
    description: 'Tells whether Twig\'s profiler extensions are handed out per coroutine.',
)]
final class TwigProfilerExtensionsCheckCommand extends Command
{
    /**
     * Named by the same string the compiled templates carry: the node visitor each of these registers
     * looks itself up by class, so the class is what a render actually reaches for.
     */
    private const array PROFILER_EXTENSIONS = [
        ProfilerExtension::class,
        WebProfilerExtension::class,
    ];

    public function __construct(
        #[Autowire(service: 'twig')]
        private readonly Environment $twig,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (self::PROFILER_EXTENSIONS as $extensionClass) {
            $output->writeln(sprintf(
                'coroutines %s %s.',
                $this->extensionIsSharedAcrossCoroutines($extensionClass) ? 'SHARE' : 'DO NOT SHARE',
                $extensionClass,
            ));
        }

        return self::SUCCESS;
    }

    /**
     * Two coroutines each take an Environment out of the pool and report the extension they would be
     * rendering through.
     *
     * @param class-string $extensionClass
     */
    private function extensionIsSharedAcrossCoroutines(string $extensionClass): bool
    {
        $twig = $this->twig;

        $resolveExtension = static fn(): int => spl_object_id($twig->getExtension($extensionClass));

        $ids = CoroutinePool::fromCoroutines($resolveExtension, $resolveExtension)->run();

        return count(array_unique($ids)) === 1;
    }
}
