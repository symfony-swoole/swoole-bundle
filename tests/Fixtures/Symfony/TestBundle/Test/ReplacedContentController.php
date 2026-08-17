<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test;

use SwooleBundle\SwooleBundle\Tests\Helper\TestToken;

/**
 * The controller the reload and HMR tests rewrite to prove that a server picked their change up.
 *
 * Each test writes a marker of its own into the controller and then asserts the server answers with it,
 * so two tests rewriting one file would read each other's markers. Every worker therefore renders its
 * own controller class, on a route of its own, out of the shared template: the generated files sit side
 * by side in the same directory and no worker ever touches another's.
 */
trait ReplacedContentController
{
    private const string CONTROLLER_TEMPLATE_ORIGINAL_TEXT = 'Wrong response!';
    private const string CONTROLLER_TEMPLATE_REPLACE_TEXT = '%REPLACE%';
    private const string CONTROLLER_TEMPLATE_SRC = __DIR__
        . '/../Controller/ReplacedContentTestController.php.tmpl';
    private const string CONTROLLER_DIR = __DIR__ . '/../Controller';

    /**
     * The route the worker's own generated controller answers on.
     */
    protected function replacedContentRoute(): string
    {
        return '/test/replaced/content' . TestToken::suffix();
    }

    /**
     * Renders the worker's controller with the text every test starts from.
     *
     * A worker other than the first has no controller of its own until it writes one, and a fresh
     * checkout has only the template - so this has to run before the server boots, or the route the
     * test is about to call would not be registered at all.
     */
    protected function writeOriginalTestController(): void
    {
        $this->replaceContentInTestController(self::CONTROLLER_TEMPLATE_ORIGINAL_TEXT);
    }

    protected function replaceContentInTestController(string $text): void
    {
        $destination = $this->replacedContentControllerPath();

        file_put_contents($destination, $this->renderTestController($text));
        touch($destination);
    }

    protected function assertTestControllerResponseEquals(string $expected): void
    {
        self::assertSame(
            $this->renderTestController($expected),
            file_get_contents($this->replacedContentControllerPath()),
        );
    }

    protected function deferRestoreOriginalTemplateControllerResponse(): void
    {
        defer(function (): void {
            $this->writeOriginalTestController();
        });
    }

    private function replacedContentControllerPath(): string
    {
        return sprintf(
            '%s/ReplacedContentTestController%s.php',
            self::CONTROLLER_DIR,
            $this->controllerClassSuffix()
        );
    }

    private function renderTestController(string $text): string
    {
        return str_replace(
            [self::CONTROLLER_TEMPLATE_REPLACE_TEXT, '%CLASS_SUFFIX%', '%ROUTE_SUFFIX%'],
            [$text, $this->controllerClassSuffix(), TestToken::suffix()],
            (string) file_get_contents(self::CONTROLLER_TEMPLATE_SRC),
        );
    }

    /**
     * Class names cannot carry the dash the other suffixes use, so the worker number stands alone:
     * ReplacedContentTestController2 for the second worker, the plain name for a serial run.
     */
    private function controllerClassSuffix(): string
    {
        return TestToken::isParallel() ? (string) TestToken::current() : '';
    }
}
