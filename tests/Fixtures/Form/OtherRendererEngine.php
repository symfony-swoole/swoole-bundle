<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Form;

use Symfony\Component\Form\AbstractRendererEngine;
use Symfony\Component\Form\FormView;

/**
 * A form renderer engine that is not Twig's, standing in for an application that renders forms through
 * something else - the case the wider engine reset must not be applied to, since the properties it
 * undoes belong to TwigRendererEngine.
 */
final class OtherRendererEngine extends AbstractRendererEngine
{
    /**
     * @param array<string, mixed> $variables
     */
    public function renderBlock(FormView $view, mixed $resource, string $blockName, array $variables = []): string
    {
        return '';
    }

    protected function loadResourceForBlockName(string $cacheKey, FormView $view, string $blockName): bool
    {
        return false;
    }
}
