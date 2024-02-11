<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Command;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use UnexpectedValueException;

/**
 * @property ParameterBagInterface $parameterBag
 */
trait ParametersHelper
{
    protected function getProjectDirectory(): string
    {
        $projectDir = $this->parameterBag->get('kernel.project_dir');

        if (!is_string($projectDir)) {
            throw new UnexpectedValueException('Invalid project directory.');
        }

        return $projectDir;
    }
}
