<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Profiler\Profile;

/**
 * Reports whether the root of Twig's profiling tree is one per coroutine.
 *
 * Every render enters and leaves nodes of that profile, and the data collector empties it at the end of
 * the request - so one shared profile has concurrent renders nesting inside each other and the first
 * request to finish discarding what the rest are still building.
 */
#[AsCommand(
    name: 'test:twig-profile:proxy-check',
    description: 'Tells whether Twig\'s profile is handed out per coroutine.',
)]
final class TwigProfileProxyCheckCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'twig.profile')]
        private readonly Profile $profile,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf(
            'twig profile %s proxified.',
            // Profile is declared final, so nothing can implement ContextualProxy as far as static
            // analysis is concerned - the proxy generator strips final off the class it extends.
            // @phpstan-ignore instanceof.alwaysFalse
            $this->profile instanceof ContextualProxy ? 'IS' : 'IS NOT',
        ));

        $output->writeln(sprintf(
            'coroutines %s the profile.',
            $this->profileIsSharedAcrossCoroutines() ? 'SHARE' : 'DO NOT SHARE',
        ));

        return self::SUCCESS;
    }

    /**
     * Two coroutines each take their instance out of the pool and report which profile they would be
     * writing their render tree into.
     */
    private function profileIsSharedAcrossCoroutines(): bool
    {
        $profile = $this->profile;

        $resolveProfile = static function () use ($profile): array {
            // @phpstan-ignore instanceof.alwaysFalse
            $contextual = $profile instanceof ContextualProxy ? $profile->getContextualObject() : $profile;

            return [spl_object_id($contextual)];
        };

        $profiles = array_merge(...CoroutinePool::fromCoroutines($resolveProfile, $resolveProfile)->run());

        return count(array_unique($profiles)) === 1;
    }
}
