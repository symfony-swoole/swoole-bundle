<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Runtime;

use Assert\Assertion;
use SwooleBundle\SwooleBundle\Component\GeneratedCollection;
use SwooleBundle\SwooleBundle\Server\RequestHandler\RequestHandler;

final class CallableBootManagerFactory
{
    /**
     * @param iterable<Bootable&RequestHandler> $bootableCollection
     */
    public function make(iterable $bootableCollection, Bootable ...$bootables): CallableBootManager
    {
        $objectRegistry = [];
        // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference
        $isAlreadyRegistered = static function (int $id) use (&$objectRegistry): bool {
            $result = !isset($objectRegistry[$id]);
            $objectRegistry[$id] = true;

            return $result;
        };

        return new CallableBootManager(
            (new GeneratedCollection($bootableCollection, ...$bootables))
                ->filter(static function ($bootable) use ($isAlreadyRegistered): bool {
                    Assertion::isInstanceOf($bootable, Bootable::class);

                    return $isAlreadyRegistered(spl_object_id($bootable));
                })
                ->map(static fn(Bootable $bootable): callable => $bootable->boot(...))
        );
    }
}
