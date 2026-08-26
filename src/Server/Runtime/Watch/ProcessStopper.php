<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Runtime\Watch;

use Symfony\Component\Process\Process;

/**
 * Stops a spawned process together with everything it spawned.
 *
 * Process::stop() signals the process that was started and nothing else. For a swoole server that is
 * the master alone, and it is enough only while the master exits on the SIGTERM, taking its manager and
 * workers with it. When it does not - workers mid-request, a raised max_wait_time - Process::stop()
 * reaches the end of its timeout and sends SIGKILL, which cannot be handled and so cannot be passed on.
 * The manager is reparented to init, keeps its workers, and they keep the listen socket. Whatever tries
 * to bind that port next fails, and goes on failing.
 *
 * So the tree is read before anything is signalled - once the master is gone its children have been
 * reparented and nothing connects them to it any more - and whatever survives is killed explicitly.
 */
final readonly class ProcessStopper
{
    /**
     * How long anything killed is given to disappear before the caller is allowed to continue.
     */
    private const float REAP_TIMEOUT_S = 5.0;

    private const int SIGNAL_KILL = 9;

    public function __construct(private ProcessTree $tree = new ProcessTree()) {}

    /**
     * Stops the process and returns what had to be killed, which is empty when it stopped on its own.
     *
     * @param float $timeout seconds the process is given before it is taken apart by force
     * @return list<int> pids that outlived the stop and were killed
     */
    public function stop(Process $process, float $timeout): array
    {
        $pid = $process->getPid();
        $tree = $pid === null ? [] : $this->tree->of($pid);

        if ($process->isRunning()) {
            $process->stop($timeout);
        }

        $killed = $this->tree->kill($tree, self::SIGNAL_KILL);

        if ($killed !== []) {
            $this->awaitGone($killed);
        }

        return $killed;
    }

    /**
     * Nothing may take the port until these are gone, so the caller waits for them rather than racing
     * them.
     *
     * @param list<int> $pids
     */
    private function awaitGone(array $pids): void
    {
        $deadline = microtime(true) + self::REAP_TIMEOUT_S;

        while (microtime(true) < $deadline) {
            if (array_filter($pids, $this->tree->isRunning(...)) === []) {
                return;
            }

            usleep(50_000);
        }
    }
}
