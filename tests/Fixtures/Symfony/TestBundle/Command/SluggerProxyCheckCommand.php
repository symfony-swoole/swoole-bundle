<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'test:slugger:proxy-check',
    description: 'Tells whether the slugger service has been proxified and whether it is still usable.',
)]
final class SluggerProxyCheckCommand extends Command
{
    public function __construct(private readonly SluggerInterface $slugger)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isProxified = $this->slugger instanceof ContextualProxy ? 'IS' : 'IS NOT';

        $output->writeln(sprintf('Slugger %s proxified.', $isProxified));
        $output->writeln(sprintf('Slug: %s', $this->slugger->slug('Hello World')));

        return self::SUCCESS;
    }
}
