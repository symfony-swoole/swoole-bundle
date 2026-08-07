<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;

/**
 * Reports whether SecurityBundle's access decision manager was made coroutine-safe and still decides.
 *
 * The manager keeps the decision being made on a stack of its own, so one shared instance cannot serve
 * two coroutines at once - see SecurityProcessor.
 */
#[AsCommand(
    name: 'test:access-decision-manager:proxy-check',
    description: 'Tells whether the access decision manager has been proxified and still decides.',
)]
final class AccessDecisionManagerProxyCheckCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'security.access.decision_manager')]
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isProxified = $this->accessDecisionManager instanceof ContextualProxy ? 'IS' : 'IS NOT';
        $output->writeln(sprintf('access decision manager %s proxified.', $isProxified));

        // an anonymous token holds no roles, so this is denied - what matters is that a decision is
        // reached at all, through the pushing and popping of the stack the whole fix is about
        $granted = $this->accessDecisionManager->decide(new NullToken(), ['ROLE_USER']);

        $output->writeln(sprintf('access decision %s.', $granted === false ? 'WORKS' : 'FAILED'));

        $output->writeln(sprintf(
            'coroutines %s the stack.',
            $this->stackIsSharedAcrossCoroutines() ? 'SHARE' : 'DO NOT SHARE',
        ));

        return self::SUCCESS;
    }

    /**
     * Two coroutines each take their instance out of the pool and report which manager owns the stack
     * they would be pushing onto. Getting the same one back both times means a single stack serves both.
     *
     * Being proxified is not enough to answer that with kernel.debug on: what is pooled there is the
     * TraceableAccessDecisionManager, pooled all along for being resettable, while the manager holding
     * the stack sits behind it - so the one behind it is what has to be looked at.
     */
    private function stackIsSharedAcrossCoroutines(): bool
    {
        $manager = $this->accessDecisionManager;

        $resolveStackOwner = static function () use ($manager): array {
            $contextual = $manager instanceof ContextualProxy ? $manager->getContextualObject() : $manager;

            if ($contextual instanceof TraceableAccessDecisionManager) {
                /** @var object $contextual */
                $contextual = (new ReflectionProperty(TraceableAccessDecisionManager::class, 'manager'))
                    ->getValue($contextual);
            }

            return [spl_object_id($contextual)];
        };

        $stackOwners = array_merge(
            ...CoroutinePool::fromCoroutines($resolveStackOwner, $resolveStackOwner)->run(),
        );

        return count(array_unique($stackOwners)) === 1;
    }
}
