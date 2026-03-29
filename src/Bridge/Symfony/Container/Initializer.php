<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container;

interface Initializer
{
    public function initialize(object $service): void;
}
