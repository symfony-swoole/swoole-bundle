<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Runtime\HMR;

use Override;
use Psr\Log\LoggerInterface;
use Swoole\Server;

/**
 * Keeps HMR from quietly doing nothing about a change it cannot apply.
 *
 * Reloading workers applies a change to a class no worker had loaded yet, and that is the whole of what
 * it can do. Two kinds of change fall outside it, and both are invisible to the reloaders underneath:
 *
 *  - a file the master had already loaded when it forked - the kernel, the bundles, the compiler
 *    passes, and every service class that boot touched. Every reloader excludes those from its watch
 *    set, because PHP cannot redeclare a class the forked worker already holds.
 *  - anything that changes the container - config files, routes, env. Those are not included files at
 *    all, so they never enter a watch set built from get_included_files() in the first place.
 *
 * Saving one of those and watching nothing happen is the worst version of this: the change did not
 * apply, and nothing said so. So the conditions are asked first, and one that answers is reported
 * rather than reloaded around - a reload would drop every connection the workers hold and still leave
 * the old code in place.
 *
 * Reported once per generation of staleness. The tick runs every couple of seconds and nothing gets
 * better until somebody restarts the server, which would otherwise be a line every tick for as long as
 * the developer took to read the first one.
 *
 * @see RestartCondition for the individual checks
 */
final class RestartAwareHotModuleReloader implements HotModuleReloader
{
    private bool $reported = false;

    /**
     * @param iterable<RestartCondition> $conditions
     */
    public function __construct(
        private readonly HotModuleReloader $decorated,
        private readonly iterable $conditions,
        private readonly LoggerInterface $logger,
    ) {}

    #[Override]
    public function tick(Server $server): void
    {
        $reason = $this->reasonForRestart();

        if ($reason !== null) {
            $this->reportOnce($reason);

            return;
        }

        // Nothing stands in the way any more, which only happens once a new process has replaced this
        // one - so the next thing that does is worth saying out loud again.
        $this->reported = false;

        $this->decorated->tick($server);
    }

    /**
     * The first condition with something to say. They are not all collected: any one of them means the
     * same thing for the reader, and the rest would only add noise to the line they read.
     */
    private function reasonForRestart(): ?string
    {
        foreach ($this->conditions as $condition) {
            $reason = $condition->reasonForRestart();

            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }

    private function reportOnce(string $reason): void
    {
        if ($this->reported) {
            return;
        }

        $this->reported = true;

        $this->logger->warning(sprintf(
            'Hot module reload is paused because %s. Reloading the workers cannot apply this, so the '
            . 'server has to be restarted - run it under "swoole:server:watch" to have that done for '
            . 'you.',
            $reason,
        ));
    }
}
