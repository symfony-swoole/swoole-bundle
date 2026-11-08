<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Container;

/**
 * Lets go of what an instance holds outside the process when its service pool drops it for good: one beyond the
 * pool's idle limit, one its stability checker rejected, one the pool drains at the end of a worker.
 *
 * Without one, a dropped instance lets go of its resources when PHP collects it - at once, or only at the next cycle
 * collection where it is part of a cycle. For an instance that holds a server's session, such as a database
 * connection, that is a session left open on the server for as long as the worker lives.
 *
 * Paired with the stateful services of its supported class, as stability checkers are.
 */
interface Disposer
{
    public function dispose(object $service): void;

    /**
     * @return class-string
     */
    public static function getSupportedClass(): string;
}
