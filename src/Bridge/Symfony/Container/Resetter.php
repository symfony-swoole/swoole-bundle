<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container;

interface Resetter
{
    public function reset(object $service): void;
}
