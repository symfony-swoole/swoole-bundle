<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Runtime\HMR;

/**
 * One reason a change cannot be applied by reloading the workers.
 *
 * Reloading re-forks the workers from the master's memory image, which applies a change to a class no
 * worker had loaded yet and nothing else. There is more than one way to fall outside that, and what
 * every caller wants is the single question "will a reload do?" rather than a list of checks to run
 * itself - so the conditions are asked together and the first one with an answer speaks for all.
 */
interface RestartCondition
{
    /**
     * Why the server has to be restarted, or null when a reload is still enough.
     *
     * Phrased to complete the sentence "hot module reload is paused because ...", because that is where
     * it is read.
     */
    public function reasonForRestart(): ?string;
}
