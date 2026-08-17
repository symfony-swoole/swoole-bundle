# Long running commands in task workers

> **EXPERIMENTAL.** Both modes described here - with coroutines and without - are experimental.
> The configuration keys, the service names and the shutdown behaviour may all change without a
> major release, and neither mode has production mileage behind it yet. Treat what follows as
> something to try in a staging environment, not as a supported deployment shape.
>
> The [Swoole task transport](swoole-task-symfony-messenger-transport.md) it shares task workers
> with is likewise not a hardened pairing: they are known to coexist (see
> [Sharing task workers](#sharing-task-workers-with-the-task-transport)), not known to coexist well
> under load.

A messenger consumer normally runs as its own container under a supervisor. This runs it inside a
Swoole task worker instead, so one server process supervises both the HTTP workers and the
consumers, and a deployment ships one image rather than two.

The shape that motivates it is [the worker unit](#the-worker-unit): a single deployable running
several worker loops at once **and** answering HTTP health checks throughout - which a supervisor
deployment cannot give you, because a `messenger:consume` process runs one loop and has no HTTP
surface for a probe to talk to.

```yaml
swoole:
  task_worker:
    commands:
      - 'messenger:consume default scheduling --memory-limit=512M'
```

Anything on the console works, not only messenger:

```yaml
swoole:
  task_worker:
    commands:
      - 'app:projection:run --memory-limit=512M'
```

## The worker unit

The reason to want this is a deployable that a supervisor-and-messenger deployment cannot express: a
single unit that **answers HTTP health checks while running several worker loops at once**.

Today those two properties pull against each other. A `messenger:consume` container is one process
running one loop, and it has no HTTP surface, so there is nothing for a liveness probe to talk to -
an orchestrator is left asking whether the process exists, which a consumer wedged on a dead broker
socket answers just as well as a healthy one. Running several consumers means several containers, or
a supervisor running several processes inside one, which puts the individual failures back out of
sight and still leaves nothing to probe. Bolting an HTTP server onto a consumer, or running a
sidecar, buys the probe back at the cost of another moving part.

A server with task worker commands is one process tree that has both:

```yaml
swoole:
  http_server:
    healthcheck:
      enabled: true
      port: 9300
    settings:
      worker_count: 1        # nothing routes to it; it is here to hold the server up
  platform:
    coroutines:
      enabled: true
  task_worker:
    settings:
      worker_count: 1
    commands:
      -
        - 'messenger:consume default --memory-limit=170M'
        - 'messenger:consume scheduling --memory-limit=170M'
        - 'app:projection:run --memory-limit=170M'
```

Three loops in one task worker process, and `/healthz` on port 9300 answering throughout. The
[liveness endpoint](swoole-health.md) is served by its own process rather than by the worker pool,
so it keeps answering while every loop is busy - which is the whole point, since a probe that queues
behind the work it is monitoring reports a busy unit as a dead one.

What that buys over a supervisor deployment:

- **One image, one deployable** for the API and the consumers, rather than one per consumer.
- **A real liveness probe** on a worker-only unit. Orchestrators can restart it on the same signal
  they use for anything else.
- **Several loops per process**, so a fleet of low-traffic consumers stops costing a container each.
- **Restart on `--memory-limit` handled by Swoole's manager** rather than by supervisord - see
  [Memory limits and restarts](#memory-limits-and-restarts).
- **Checks you contribute yourself.** Implementing `HealthCheck` lets the unit report on its own
  work rather than only on being alive.

### What you give up

Be deliberate about this - the isolation a container-per-consumer gives you is real, and this trades
some of it away.

- **The unit is the blast radius.** One probe covers the whole unit, so a check that fails because
  one loop is stuck restarts every loop in that unit. Per-consumer containers fail one at a time.
- **Every replica runs every configured command.** Scaling to three pods runs three copies of each
  loop. Right for a messenger consumer, wrong for anything that must be a singleton.
- **Scaling is per unit, not per consumer.** You cannot give the busy queue four replicas and the
  quiet one a single replica without splitting them into separate units - which is a perfectly good
  answer, and puts you back to one deployable per group rather than one per consumer.

### Reporting on the loops themselves

A `HealthCheck` runs in the **health evaluator process**, which is forked from the server master. It
is not in the task worker, so it cannot see a property the consumer set, and a check that reaches
for one is reading its own copy of that memory rather than the loop's.

To report on a loop, have the loop write to shared memory and have the check read it - a
`Swoole\Atomic` holding a last-progress timestamp is usually enough. Allocate it before the workers
are forked, the same way [`WorkerStopSignal`](#stopping) is:

```php
final readonly class ConsumerProgressCheck implements HealthCheck
{
    public function __construct(private Atomic $lastMessageAt) {}

    public function name(): string
    {
        return 'consumer_progress';
    }

    public function check(): HealthCheckResult
    {
        $last = $this->lastMessageAt->get();

        if ($last > 0 && time() - $last > 300) {
            return HealthCheckResult::unhealthy('no message handled in 5 minutes');
        }

        return HealthCheckResult::healthy();
    }
}
```

Mind what "no progress" means for the queue in question before wiring it to a probe: on a queue that
is legitimately empty overnight, that check restarts a healthy unit every five minutes.

## One group, one task worker

Each entry in `commands` claims a task worker of its own. An entry that is a list runs those
commands **side by side in a single task worker**, each in its own coroutine:

```yaml
swoole:
  platform:
    coroutines:
      enabled: true
  task_worker:
    settings:
      worker_count: 4        # optional; defaults to the number of groups
    commands:
      - 'messenger:consume default --memory-limit=512M'      # task worker 0
      -                                                       # task worker 1
        - 'messenger:consume scheduling --memory-limit=170M'
        - 'messenger:consume scheduling --memory-limit=170M'
        - 'app:projection:run --memory-limit=170M'
```

`worker_count` may be left out, in which case it becomes the number of groups. Setting it lower
than the number of groups is a configuration error - every group needs a worker of its own. Setting
it higher is fine: the extra task workers have no group and stay ordinary task workers.

Grouping more than one command into a single entry **requires `platform.coroutines.enabled`** and is
rejected at compile time otherwise. Without a scheduler the one command blocks the worker and owns
the process, so a second command in the same group could never start.

## What the two modes actually do

**Coroutines on.** Each command is spawned into its own coroutine and the worker start hook returns
immediately, so the task worker goes on running its reactor. It keeps serving tasks, its lifecycle
callbacks keep firing, and shutdown is prompt.

**Coroutines off.** There is no scheduler to spawn into, so the single command blocks the worker
start hook and the task worker is dedicated to it. It serves no tasks, and it reaches none of its
own lifecycle callbacks until the command returns.

The coroutine mode is the one to reach for. The blocking mode exists because a single consumer per
process is a legitimate shape, but it gives up a good deal - see [Stopping](#stopping).

## Stopping

This is the part worth understanding before deploying any of it.

**Signals do not work inside a Swoole worker.** Swoole claims the worker signals for itself. A pcntl
handler registered in a worker - which is what `Symfony\Component\Console\Application` installs for
any command implementing `SignalableCommandInterface`, and what `messenger:consume` relies on -
reports success and then never fires, because the signal is drained through Swoole's own reactor.
Nothing warns you: the command simply runs until it is killed.

So the bundle does not run commands through `Application::run()`, which is where that registration
happens, and re-delivers the stop by hand instead:

1. A worker learns the server is stopping (via `onWorkerExit`) and raises a flag in shared memory.
2. With coroutines on, a watchdog coroutine per command polls that flag every 100ms and calls the
   command's own `handleSignal(SIGTERM)`. For `messenger:consume` that reaches `Worker::stop()` -
   exactly what a real signal would have done.
3. With coroutines off there is no watchdog, so the command has to notice for itself.

`onWorkerExit` and not `onWorkerStop`, because in a task worker running commands the ordering is:

```
4.009s  onWorkerExit   <- commands still running, the usable moment
4.083s  command stops
4.084s  onWorkerStop   <- only now, because it waits for the coroutines to finish
```

Waiting for `onWorkerStop` would mean waiting for the very thing the flag exists to bring about.

### What your command needs to do

**Commands implementing `SignalableCommandInterface`** work as they are, with coroutines on. That
covers `messenger:consume` and anything following the same pattern.

**Messenger consumers** work in both modes: the bundle also registers a listener on messenger's
`WorkerRunningEvent`, which fires between messages, so the consumer stops itself at a point where
the message in flight has been finished and acked.

**Anything else, with coroutines off,** has to bring its own cooperative check. There is no watchdog
to hand it a signal and no event to hook, so a command that only ever returns when it feels like it
will be force-terminated when `max_wait_time` expires. If you cannot add a check, do not run that
command in blocking mode.

A command that subscribes to no signals at all is logged as a warning when the stop cannot be
delivered, rather than failing quietly.

## Memory limits and restarts

`--memory-limit` only achieves anything if the process it leaked into goes away. When a command
returns, the bundle stops its worker and Swoole's manager forks a replacement, which starts the
command afresh - the same effect a supervisor restart has, without the supervisor.

Two consequences worth planning for:

- **The limit is process-wide.** `memory_get_usage()` does not know which coroutine allocated what,
  so three commands sharing a task worker with `--memory-limit=512M` each will all trip at 512M
  *total*. Divide the budget across the group, as in the example above.
- **A whole group recycles together.** When one command in a group ends, the others are asked to
  stop and the worker is recycled once they have. That avoids killing a sibling mid-message, but it
  does mean one command's memory limit restarts its group-mates too.

A group that ends in under a second is treated as broken rather than finished: the worker is not
recycled and a critical is logged. Without that, a typo in a command line would fork a replacement
in a loop for as long as the server ran.

## Sharing task workers with the task transport

With coroutines on, a task worker running commands still serves tasks, so the
[task transport](swoole-task-symfony-messenger-transport.md) goes on working in the same process.

With coroutines off it does not. The worker is blocked in its command and never reads its task pipe,
while `Swoole\Server::task()` will still dispatch to it - envelopes sent to that worker queue up and
are only handled if and when the command returns. **Do not combine blocking-mode commands with the
task transport.**

## Operational notes

- **Turn HMR off** in a server running these. A repeating `Timer::tick` keeps a worker's reactor
  non-empty, which stretches shutdown out to `max_wait_time`.
- **Set `worker_max_wait_time`** high enough for the slowest command to finish what it is doing.
  Swoole's default is 3 seconds, after which workers are force-terminated.
- **Output** from each command goes to the server's stdout on its own stream handle. Verbosity flags
  (`-v`, `-vv`, `-q`) in the command line are honoured.
- **Every command runs in every replica.** These are workers of a server, so scaling the deployment
  to three pods runs three copies of each configured command. That is usually what you want for a
  messenger consumer and rarely what you want for a singleton job.

## Requirements

- `symfony/framework-bundle`, which provides the console `Application` used to resolve commands.
- `platform.coroutines.enabled: true` for more than one command per task worker.
