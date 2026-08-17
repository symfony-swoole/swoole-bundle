<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Scheduler;

use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Http\Server;
use Swoole\Timer;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\CoWrapper;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;
use Symfony\Component\Cache\LockRegistry;
use Throwable;

/**
 * Polls a {@see Scheduler} for due messages on a Swoole {@see Timer::tick()}, instead of the
 * blocking `messenger:consume scheduler_*` worker loop Symfony ships out of the box - which
 * doesn't cooperate with Swoole's event loop the way a Timer callback does.
 */
final class WithScheduler implements Configurator
{
    private int $tickId;

    private bool $running = false;

    /**
     * @param positive-int $intervalMs How often, in milliseconds, to poll for due messages.
     * @param (callable(): void)|null $afterTick Called after every tick, whether it succeeded or
     *   not - for resetting any app-specific state that {@see CoWrapper::defer()} doesn't
     *   already cover because it's deliberately shared/global rather than per-coroutine-pooled.
     *   See docs/swoole-scheduler.md.
     */
    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly CoWrapper $coWrapper,
        private readonly LoggerInterface $logger,
        private readonly int $intervalMs = 60_000,
        private readonly mixed $afterTick = null,
    ) {}

    public function __destruct()
    {
        if (!isset($this->tickId)) {
            return;
        }

        Timer::clear($this->tickId);
    }

    public function configure(Server $server): void
    {
        // Symfony Scheduler's stateful Schedule::stateful()/Checkpoint::save() routes every tick
        // through Symfony Cache's LockRegistry, a pool of ~20 flock() locks on vendor PHP files.
        // Under a Swoole Timer::tick coroutine those locks have reliably wedged forever after a
        // handful of ticks (a bare cache write never returning, no error, no concurrency
        // involved); disabling file-based locking here avoids that outright. LockRegistry's
        // cross-process stampede protection buys nothing for a Timer::tick anyway - there's only
        // ever one process writing a given schedule's checkpoint. The trade-off is losing
        // stampede protection for every cache pool app-wide, not just the scheduler's, since
        // LockRegistry's state is static/global rather than scoped to one pool - acceptable since
        // it only guards redundant recomputation on a concurrent cache-miss race, not correctness.
        LockRegistry::setFiles([]);

        $this->tickId = Timer::tick($this->intervalMs, $this->tick(...));

        $server->on('shutdown', function (): void {
            if (! isset($this->tickId)) {
                return;
            }

            Timer::clear($this->tickId);
        });
    }

    public function tick(): void
    {
        // Defense in depth: Timer::tick fires a new coroutine every interval regardless of
        // whether the previous tick finished. If Scheduler::run() ever stalls, this keeps
        // overlapping ticks from piling up unboundedly instead of just skipping until the stuck
        // one clears.
        if ($this->running) {
            return;
        }

        $this->running = true;

        try {
            // Registers this coroutine to release/reset every pooled stateful service it
            // touches when it ends - the same mechanism request- and message-boundary handlers
            // use. Without this, nothing pooled that Scheduler::run() touches (an entity
            // manager, an event dispatcher, etc.) would ever be reset between ticks.
            //
            // Guarded by getCid(): Timer::tick's callback fires exactly once in the master
            // process during configure(), but during Server::reload() it can *also* fire once in
            // the manager process mid-reload, with no coroutine underneath it
            // (Coroutine::getCid() === -1 there). CoWrapper::defer() throws in that spillover
            // call in a way that bypasses this method's own catch below entirely and kills the
            // manager process outright, despite sitting inside this try block. getCid() is
            // documented safe to call from any context (returns -1 instead of throwing), so
            // checking first avoids ever making the call that can't be reliably caught -
            // skipping pooled-service reset for one tick is a far smaller cost than that.
            if (Coroutine::getCid() === -1) {
                $this->logger->warning(
                    'Scheduler tick has no coroutine context (likely mid worker reload/recycle) - '
                    . 'skipping pooled-service reset for this tick',
                );
            } else {
                $this->coWrapper->defer();
            }

            $this->scheduler->run();
        } catch (Throwable $e) {
            // Timer::tick has no caller to propagate an exception to - nothing catches it, so
            // PHP's default uncaught-exception handling kicks in, which under Swoole's coroutine
            // hooking crashes the *entire process*, not just this coroutine. A single failed
            // tick must not be allowed to bring the whole server down; log it and let the next
            // tick retry.
            $this->logger->error('Scheduler tick failed', [
                'exception' => $e,
            ]);
        } finally {
            if ($this->afterTick !== null) {
                ($this->afterTick)();
            }

            $this->running = false;
        }
    }
}
