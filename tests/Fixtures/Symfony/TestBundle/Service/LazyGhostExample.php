<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

/**
 * A lazy service, shared and not pooled, which is the shape the generated factories get wrong.
 *
 * Symfony builds it as a native lazy ghost: the first call hands back an empty instance and stores it,
 * and the constructor only runs later, when something touches the object and PHP calls the initializer -
 * which calls the very same factory back, passing the ghost where the flag saying what to return goes.
 *
 * The property is typed and promoted on purpose. A ghost that never had its constructor run looks like
 * any other instance until something reads one, and then says so as an Error about the property rather
 * than as anything mentioning laziness.
 */
// phpcs:ignore SlevomatCodingStandard.Classes.ReadonlyClass,SlevomatCodingStandard.Classes.RequireAbstractOrFinal.ClassNeitherAbstractNorFinal
class LazyGhostExample
{
    public function __construct(private readonly string $name = 'lazy ghost') {}

    public function describe(): string
    {
        return sprintf('%s is constructed', $this->name);
    }
}
