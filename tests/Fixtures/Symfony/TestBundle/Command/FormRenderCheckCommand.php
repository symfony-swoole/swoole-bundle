<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Command;

use Override;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Throwable;
use Twig\BlockChain;
use Twig\Environment;

/**
 * Renders a form in two coroutines and says whether each of them got a form back.
 *
 * The form is the point: from symfony/twig-bridge 7.4.19 and 8.1.7 on, wherever Twig has BlockChain, the
 * renderer engine composes the themes into one and builds it with the Environment it was given. Under this
 * bundle that Environment is the pooled service standing in for the coroutine's own, and Twig 3.29 refuses
 * a chain whose templates were loaded by another Environment than the one it was built with - so the render
 * ends in "A block chain cannot contain templates from different Twig environments." rather than in html.
 *
 * Two coroutines rather than one because the second is what a bound-at-construction fix would still fail:
 * an engine holding the first coroutine's Environment renders for the second one too, the pool having
 * handed the instance on.
 */
#[AsCommand(
    name: 'test:form:render-check',
    description: 'Renders a form in two coroutines and reports what came back.',
)]
final class FormRenderCheckCommand extends Command
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly Environment $environment,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('block_chains=%s', class_exists(BlockChain::class) ? 'yes' : 'no'));

        /** @var list<string> $renders */
        $renders = CoroutinePool::fromCoroutines(
            fn(): string => $this->render('first'),
            fn(): string => $this->render('second'),
        )->run();

        $output->writeln(sprintf('coroutines=%d', count($renders)));

        foreach ($renders as $render) {
            $output->writeln($render);
        }

        return self::SUCCESS;
    }

    private function render(string $which): string
    {
        $form = $this->formFactory->createBuilder()
            ->add($which, TextType::class)
            ->getForm();

        try {
            $html = $this->environment->render('form.html.twig', ['form' => $form->createView()]);
        } catch (Throwable $throwable) {
            return sprintf('%s=failed: %s', $which, $throwable->getMessage());
        }

        return sprintf(
            '%s=%s',
            $which,
            str_contains($html, sprintf('name="form[%s]"', $which)) ? 'rendered' : 'no field: ' . trim($html),
        );
    }
}
