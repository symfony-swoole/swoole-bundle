<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\Command;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @property ParameterBagInterface $parameterBag
 */
trait ParametersHelperTrait
{
    protected function getProjectDirectory(): string
    {
        $projectDir = $this->parameterBag->get('kernel.project_dir');

        if (!\is_string($projectDir)) {
            throw new \UnexpectedValueException('Invalid project directory.');
        }

        return $projectDir;
    }
}
