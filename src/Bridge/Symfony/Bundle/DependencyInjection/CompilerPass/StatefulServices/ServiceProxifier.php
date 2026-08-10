<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices;

/**
 * The part of the proxifier a {@see CompileProcessor} is given.
 *
 * Kept separate from {@see Proxifier} so a processor can be tested for what it does to the container
 * without also standing up what proxifying needs. The real one reads the class it is about to replace
 * through z-engine, and z-engine initializes once per PHP process - which is not something a unit test
 * can ask for, since it would decide the engine's state for every test running after it in that process.
 */
interface ServiceProxifier
{
    /**
     * @param string|null $externalResetter service id of a resetter to run when the instance is
     *                                      returned to the pool, or null to reset it by its own means
     */
    public function proxifyService(string $serviceId, ?string $externalResetter = null, int $resetPriority = 0): void;
}
