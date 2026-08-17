# Swoole Server Scheduler (Symfony Scheduler)

## Why

[Symfony Scheduler](https://symfony.com/doc/current/scheduler.html) is normally polled by running `messenger:consume scheduler_<name>` as a separate, permanently-running worker process, blocking between polls on a `sleep()`-based wait. That doesn't cooperate with Swoole's event loop: it needs its own process (and its own supervision, restarts, and memory) outside of the Swoole server entirely.

This integration polls the same schedules from *inside* the Swoole server instead, using a `Swoole\Timer::tick()` callback rather than a blocking worker loop - no second process to run or supervise. It's disabled by default and opt-in per app.

## Usage

1. Install `symfony/scheduler` and `symfony/messenger` in your application (if not already) and define at least one `#[AsSchedule]` schedule provider, following the [official Symfony Scheduler guide](https://symfony.com/doc/current/scheduler.html).

    ```sh
    composer require symfony/scheduler symfony/messenger
    ```

2. Enable the scheduler configurator in `swoole.yaml`:

    ```yaml
    # config/packages/swoole.yaml
    swoole:
        http_server:
            services:
                scheduler:
                    enabled: true
                    interval: 60 # seconds between polls, default is 60, must be >= 1
    ```

That's it - every `#[AsSchedule]`-tagged `ScheduleProviderInterface` service in your app is polled automatically, the same way `messenger:consume scheduler_*` would poll it, just off the Swoole event loop instead of a separate process.

## Implementation Notes

-   Messages are dispatched through the message bus with the same `PreRunEvent` / `PostRunEvent` / `FailureEvent` events Symfony's own scheduler worker dispatches, so listeners written against those events (including a `PreRunEvent` listener that cancels a run, or a `FailureEvent` listener that swallows a failure instead of letting it propagate) behave the same way here as they would under `messenger:consume`.
-   A tick that throws is caught, logged, and does not crash the server - the next tick retries a second poll interval later. Overlapping ticks (a poll that's still running when the next interval elapses) are skipped rather than piled up.
-   `Symfony\Component\Cache\LockRegistry`'s file-based locking is disabled while this is enabled (`LockRegistry::setFiles([])`, applied bundle-wide, not just to the scheduler's own cache pool). Symfony Scheduler's stateful `Schedule::stateful()`/`Checkpoint::save()` routes every tick through it, and its `flock()`-based locks have been observed to wedge forever under a `Timer::tick` coroutine. This trades away Symfony Cache's cross-process stampede protection for every cache pool in the app in exchange for the scheduler not hanging - acceptable since a `Timer::tick` is the only process ever writing its own checkpoint anyway, so there was no real stampede to protect against in the first place.
-   `WithScheduler` takes an optional `$afterTick` callable, invoked after every tick whether it succeeded or not - for resetting any state your app keeps that isn't tied to a request or message boundary `CoWrapper::defer()` already covers (e.g. a shared, non-pooled service that would otherwise accumulate for as long as the server runs). The bundle doesn't wire anything into this by default; register `WithScheduler` yourself with your own `$afterTick` in `services.yaml` if you need it.
-   If your app needs to run something before schedules are polled for due messages on every tick - e.g. proactively checking a database connection is still alive before a stateful schedule's checkpoint is read - implement `SwooleBundle\SwooleBundle\Bridge\Symfony\Scheduler\Scheduler` yourself (composing the bundle's own `DefaultScheduler` internally, or replacing it outright) and register it under the `Scheduler::class` service id in your own `services.yaml`, the same way you'd swap out `SwooleBundle\SwooleBundle\Server\HttpServerConfiguration`'s default implementation:

    ```yaml
    # config/services.yaml
    SwooleBundle\SwooleBundle\Bridge\Symfony\Scheduler\Scheduler:
        class: App\Infrastructure\Swoole\ConnectionCheckingScheduler
    ```
