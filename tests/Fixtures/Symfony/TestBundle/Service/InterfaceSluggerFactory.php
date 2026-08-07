<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Allows to define the "slugger" service with an interface as its definition class.
 */
final class InterfaceSluggerFactory
{
    public static function newSlugger(): SluggerInterface
    {
        return new AsciiSlugger();
    }
}
