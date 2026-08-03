# Liveness endpoint

A Swoole server answers requests from a fixed pool of workers. Once every worker is busy,
new connections queue, and a liveness probe pointed at the API port queues with them. An
orchestrator reading that timeout as "the process is dead" restarts a container that was
merely busy, which takes capacity away from a service that was already short of it.

The liveness endpoint sidesteps the pool. It is served by a process added with
`Swoole\Server::addProcess()`, not by a second listener: a listener would be dispatched to
the same workers and would queue in exactly the same way.

```yaml
swoole:
  http_server:
    healthcheck: true
    # equals to:
    # healthcheck:
    #     enabled: true
    #     host: 0.0.0.0
    #     port: 9300
    #     path: /healthz
```

```console
$ curl -i http://localhost:9300/healthz
HTTP/1.1 200 OK
Content-Type: application/json

{"ok":true}
```

The process is supervised by the server manager, the same as a worker: kill it and it comes
back. Anything other than the configured path answers `404`.

That answer is deliberately static. It reports one thing - this server has a process able to
accept a connection and reply - and that is the question a liveness probe asks. Checking a
database from a liveness probe turns a dependency blip into a restart of every replica at
once, which is usually worse than the blip.

## Adding checks

When you do want the endpoint to reflect more than that, implement `HealthCheck`:

```php
use SwooleBundle\SwooleBundle\Server\Health\HealthCheck;
use SwooleBundle\SwooleBundle\Server\Health\HealthCheckResult;

final readonly class DiskSpaceCheck implements HealthCheck
{
    public function name(): string
    {
        return 'disk_space';
    }

    public function check(): HealthCheckResult
    {
        $free = disk_free_space('/');

        if ($free === false || $free < 100 * 1024 * 1024) {
            return HealthCheckResult::unhealthy('less than 100MB free');
        }

        return HealthCheckResult::healthy();
    }
}
```

Implementations are autoconfigured, so registering the service is enough. Checks are
reported alongside the static answer, and one of them failing takes the endpoint with it:

```console
$ curl -i http://localhost:9300/healthz
HTTP/1.1 503 Service Unavailable
Content-Type: application/json

{"ok":false,"checks":{"disk_space":{"ok":false,"detail":"less than 100MB free"}}}
```

## How checks are run

Checks are **not** evaluated while a probe is being served. A second process sweeps them on
an interval and records each verdict in shared memory; the endpoint answers from that
record and never waits for a check.

```
master ─┬─ manager ─┬─ worker(s)
        │           ├─ health endpoint    accept loop, reads the recorded verdict
        │           └─ health evaluator   sweeps the checks, writes the verdict
```

This is what keeps a check from doing to the endpoint what a saturated pool does to the API
port. A check that hangs cannot delay a probe; it can only stop the verdict being refreshed.
A verdict older than `staleness_threshold` is reported as unhealthy in its own right:

```console
$ curl -i http://localhost:9300/healthz
HTTP/1.1 503 Service Unavailable

{"ok":false,"stale":true,"checks":{"disk_space":{"ok":true,"detail":""}}}
```

```yaml
swoole:
  http_server:
    healthcheck:
      enabled: true
      checks:
        # seconds between two passes over every registered check
        interval: 5
        # seconds after which a verdict no longer counts as current
        staleness_threshold: 15
```

A sweep counts as finished only once every check has been visited, so a sweep that dies half
way through reads as stale rather than as partially fresh. The verdict is also stale between
server start and the end of the first sweep - size the startup probe accordingly.

The evaluating process only exists when at least one check is registered. With none, the
endpoint costs a single process and answers exactly as it does above.

## What a check may touch

The evaluating process is forked from the server master, so it inherits the master's open
file descriptors. A connection opened before the fork is shared with the master and every
worker, and two processes reading the same socket corrupt each other's traffic.

Checks are resolved from the container inside the forked process, on the first sweep, which
keeps anything they construct out of the parent. Keep it that way: open what a check needs
inside `check()` rather than in a constructor that could run earlier.
