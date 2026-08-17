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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Reports whether the authorization checker was made coroutine-safe and still answers.
 *
 * The checker keeps the decision being made on $accessDecisionStack and the user being asked about on
 * $tokenStack, pushing on the way into isGranted() and popping in a finally - the same shape as the
 * access decision manager it calls into, and the one templates reach through `is_granted()`.
 */
#[AsCommand(
    name: 'test:authorization-checker:proxy-check',
    description: 'Tells whether the authorization checker has been proxified and still answers.',
)]
final class AuthorizationCheckerProxyCheckCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'security.authorization_checker')]
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isProxified = $this->authorizationChecker instanceof ContextualProxy ? 'IS' : 'IS NOT';
        $output->writeln(sprintf('authorization checker %s proxified.', $isProxified));

        // nobody is logged in, so this is denied - what matters is that an answer is reached at all,
        // through the pushing and popping of the stacks the whole fix is about
        $granted = $this->authorizationChecker->isGranted('ROLE_USER');

        $output->writeln(sprintf('authorization check %s.', $granted === false ? 'WORKS' : 'FAILED'));

        $output->writeln(sprintf(
            'coroutines %s the stacks.',
            $this->stacksAreSharedAcrossCoroutines() ? 'SHARE' : 'DO NOT SHARE',
        ));

        return self::SUCCESS;
    }

    /**
     * Two coroutines each take their instance out of the pool and report which checker owns the stacks
     * they would be pushing onto. Getting the same one back both times means a single pair serves both.
     */
    private function stacksAreSharedAcrossCoroutines(): bool
    {
        $checker = $this->authorizationChecker;

        $resolveChecker = static function () use ($checker): int {
            $contextual = $checker instanceof ContextualProxy ? $checker->getContextualObject() : $checker;

            return spl_object_id($contextual);
        };

        $checkers = CoroutinePool::fromCoroutines($resolveChecker, $resolveChecker)->run();

        return count(array_unique($checkers)) === 1;
    }
}
