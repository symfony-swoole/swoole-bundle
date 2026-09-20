<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Override;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * That a form still renders once Twig ties a template to the Environment that loaded it.
 *
 * Twig 3.29 gives every TemplateWrapper the Environment it came from and refuses it to any other, and from
 * symfony/twig-bridge 7.4.19 and 8.1.7 on the renderer engine composes the form themes into a Twig\BlockChain
 * built with the Environment it was given. Under this bundle that is the pooled service standing in for the
 * coroutine's own Environment, so the templates the chain loads belong to one object and the chain is built
 * with another:
 *
 *   A block chain cannot contain templates from different Twig environments.
 *
 * Which is every page with a form on it answering 500, on the first request, with nothing concurrent about
 * it - the shape that makes this worth a test rather than a note.
 *
 * Both coroutines are asserted because the second is what a fix applied at construction would still fail:
 * the pool hands one engine instance on to whoever asks next, and by then the Environment it was built with
 * belongs to a coroutine that has finished.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Form\TwigRendererEngineEnvironmentInitializer
 */
final class TwigBlockChainFormRenderingTest extends ServerTestCase
{
    private const string ENVIRONMENT = 'coroutines';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    public function testAFormRendersInEveryCoroutineTheEngineIsHandedTo(): void
    {
        $report = $this->renderCheck();

        if (!str_contains($report, 'block_chains=yes')) {
            self::markTestSkipped('The installed Twig has no BlockChain, so there is no chain to build wrongly.');
        }

        self::assertStringContainsString('coroutines=2', $report, sprintf(
            'One of the two coroutines never reported, so nothing below says what it rendered. %s',
            $report,
        ));
        self::assertStringContainsString('first=rendered', $report, sprintf(
            'The first coroutine got no form back. %s',
            $report,
        ));
        self::assertStringContainsString('second=rendered', $report, sprintf(
            'The second coroutine got no form back, which is the engine rendering through an Environment '
            . 'that belongs to the first. %s',
            $report,
        ));
    }

    private function renderCheck(): string
    {
        $process = $this->createConsoleProcess(['test:form:render-check'], ['APP_ENV' => self::ENVIRONMENT]);
        $process->setTimeout(self::coverageEnabled() ? 60 : 30);
        $process->run();

        $this->assertProcessSucceeded($process);

        return $process->getOutput();
    }
}
