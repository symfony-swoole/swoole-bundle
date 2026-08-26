# Console commands

The bundle registers eight commands, seven under `swoole:server:` and one under `swoole:debug:`.
It also changes what Symfony's own `debug:container` reports once coroutines are on - see
[`debug:container` and the service pools](#debugcontainer-and-the-service-pools).

```bash
bin/console list swoole
```

## Prerequisites

`SwooleBundle::boot()` throws unless `APP_RUNTIME_MODE` is set, and it throws for *every* console
command in the application, not only the ones below:

```
RuntimeException: APP_RUNTIME_MODE needs to be set either in $_SERVER or $_ENV.
For usual cases the configured value should be 'web=1&worker=1' for this bundle to work properly.
```

Symfony does not set it for you. Set it in `bin/console` and `public/index.php`, above the point
where the kernel is built:

```php
$_SERVER['APP_RUNTIME_MODE'] = $_ENV['APP_RUNTIME_MODE'] = 'web=1&worker=1';
```

It is what `kernel.runtime_mode` is resolved from, so `worker=1` is also what tells Symfony it is
running inside a long-lived process rather than a request that ends.

## At a glance

| Command | What it does | Needs |
| --- | --- | --- |
| [`swoole:server:run`](#swooleserverrun) | Runs the server in the foreground | - |
| [`swoole:server:start`](#swooleserverstart) | Runs the server as a daemon | a writable pid file |
| [`swoole:server:stop`](#swooleserverstop) | Shuts a daemonized server down | the pid file |
| [`swoole:server:reload`](#swooleserverreload) | Reloads a daemonized server's workers | the pid file |
| [`swoole:server:status`](#swooleserverstatus) | Prints a running server's status and metrics | the API server |
| [`swoole:server:profile`](#swooleserverprofile) | Serves N requests, then stops | - |
| [`swoole:server:watch`](#swooleserverwatch) | Dev supervisor, restarts on file changes | - |
| [`swoole:debug:service-pools`](#swooledebugservice-pools) | Lists the services pooled per coroutine | coroutines enabled |

## Running the server

### `swoole:server:run`

Runs the server in the foreground and blocks until it is stopped. This is the command to put in a
container's `CMD`: the process the orchestrator supervises is the server itself, so it sees the
exit status and the logs directly.

```bash
bin/console swoole:server:run --host=0.0.0.0 --port=9501 --serve-static
```

On startup it prints the socket it listens on, the API socket if one is enabled, and a table of the
configuration it resolved - extension, env, debug, `user:group`, running mode, whether coroutines
and the fiber context are on, the worker/task-worker/reactor counts, `worker_max_request` and its
grace, the memory limit, the trusted hosts and proxies, and the public dir, compression settings and
upload tmp dir when those apply. It is worth reading once after any config change: it reports what
the server actually resolved rather than what the yaml asked for.

If a server is already running - which here means the configured pid file names a live process - the
command reports it and exits with status 1 rather than fighting over the port.

Quit with `Ctrl-C`.

### `swoole:server:start`

The same server, daemonized. It writes a pid file first, and fails before starting anything if that
file cannot be created or is not writable, since without it neither `stop` nor `reload` can find the
server afterwards.

```bash
bin/console swoole:server:start --pid-file=var/swoole.pid
```

`--pid-file` defaults to `%kernel.project_dir%/var/swoole.pid`. Everything else is shared with
`swoole:server:run`.

### `swoole:server:stop`

```bash
bin/console swoole:server:stop
bin/console swoole:server:stop --no-delay
```

Reads the pid out of the pid file and signals that process. By default the shutdown is graceful,
with a 10 second budget (`HttpServer::GRACEFUL_SHUTDOWN_TIMEOUT_SECONDS`) for requests in flight to
finish; `--no-delay` skips the wait and terminates immediately.

Because the pid file is how the running server is found, this only works against a server that
wrote one - one started with `swoole:server:start`, or a `swoole:server:run` whose
`swoole.http_server.settings.pid_file` is configured. Against nothing at all it reports the error
and exits 1.

### `swoole:server:reload`

```bash
bin/console swoole:server:reload
```

Signals the workers of a daemonized server to restart, without dropping the listening socket. Same
pid file rules as `swoole:server:stop`.

**It reloads less than it looks like it does.** A worker is forked from the master, so every class
the master had already loaded is already in the worker's memory, and PHP cannot redeclare a class it
holds. Only files that were *not* loaded before the server initialized are picked up. For local
development where you want an edit to take effect regardless, use
[`swoole:server:watch`](#swooleserverwatch), which restarts the server outright.

### Options shared by `run`, `start` and `profile`

| Option | Default | Meaning |
| --- | --- | --- |
| `--host` | `swoole.http_server.host` | Host to bind to; `0.0.0.0` for any |
| `--port` | `swoole.http_server.port` | Port to listen on; `0` picks a random free one |
| `-s`, `--serve-static` | off | Serve static files from the public directory |
| `--public-dir` | `%kernel.project_dir%/public` | Where those static files live |
| `--trusted-hosts` | `swoole.http_server.trusted_hosts` | Comma separated, or the option repeated |
| `--trusted-proxies` | `swoole.http_server.trusted_proxies` | Same, and `*` trusts every proxy |
| `--api` | off | Enable the API server |
| `--api-port` | `swoole.http_server.api.port` | Port for the API server |

Each of these overrides the configured value for that one run; the ones you leave out keep resolving
from `config/packages/swoole.yaml`. See the
[configuration reference](configuration-reference.md) for the settings behind them.

`--trusted-hosts` and `--trusted-proxies` each take a list, and the three ways of writing one all
mean the same thing:

```bash
bin/console swoole:server:run --trusted-proxies=10.0.0.1,10.0.0.2
bin/console swoole:server:run --trusted-proxies=10.0.0.1 --trusted-proxies=10.0.0.2
bin/console swoole:server:run --trusted-proxies=10.0.0.1,10.0.0.2 --trusted-proxies=10.0.0.3
```

A `*` anywhere among the proxies means every proxy is trusted, and the startup table reports it as
that one entry rather than as the list it came in.

> **On coroutines, prefer Symfony's own `framework.trusted_proxies`.** Setting them here switches on
> the bundle's `TrustAllProxiesRequestHandler`, which calls `Request::setTrustedProxies()` once per
> request - and those are process-wide statics, so concurrent coroutines race on them. The framework
> writes them once while the container is being built, which is the only moment there is no
> concurrency to race with.

> **Note.** These three commands call `Application::setSignalsToDispatchEvent()` with an empty set,
> which turns off Symfony's own POSIX signal dispatching: Swoole installs its own handlers and does
> not support having them taken over. `ConsoleSignalEvent` listeners therefore do not fire for them.

## `swoole:server:status`

```bash
bin/console swoole:server:status --api-host=localhost --api-port=9200
```

Asks a **running** server about itself over the API server, so the API server has to be enabled -
either with `--api` on the command that started it, or with `swoole.http_server.api` in the
configuration. The two options above default to `swoole.http_server.api.host` and `.port`.

Status and metrics are fetched in two coroutines at once, and printed as two tables:

- **status** - host, port, running mode, the master/manager/worker pids and every extra listener;
- **metrics** - requests served, uptime, active/accepted/closed connections, total/active/idle
  workers, running coroutines, tasks queued, and event loop lag (current, max and average) where the
  extension reports it.

If nothing answers on that socket the command prints
`An error occurred while connecting to the API Server. Please verify configuration.` and exits 1 -
which usually means the API server was never enabled rather than that the server is down.

## `swoole:server:profile`

```bash
bin/console swoole:server:profile 1000
```

Starts a server that serves exactly the given number of requests and then shuts itself down. That
bounded lifetime is the point: a profiler that writes its output on shutdown (Blackfire, Xdebug,
Tideways) needs the process to end on its own, and a plain `swoole:server:run` never does. The
startup table gains a `request_limit` row.

Takes every option `swoole:server:run` does.

## `swoole:server:watch`

The recommended local development entry point. It supervises `bin/console swoole:server:run` as a
child process and fully restarts it whenever a watched file changes, running `php -l` on the changed
files first and keeping the working server up when that fails.

```bash
bin/console swoole:server:watch --path=src --path=config --path=templates --interval=500
bin/console swoole:server:watch -- --host=0.0.0.0 --port=9501 --api
```

`--path` is repeatable and defaults to `src` and `config`; `--interval` is the poll interval in
milliseconds, default `1000` and never lower than `100`. Anything after `--` is forwarded verbatim
to the supervised `swoole:server:run` and re-applied on every restart. Polling is used rather than
`inotify`, so it works in Docker and on macOS with no extension.

It is a full restart every time on purpose - see
[`swoole:server:reload`](#swooleserverreload) for why a worker reload cannot apply most edits.

Fuller documentation, including exactly which file types count as a change, is in
[Local development](docker-usage.md#local-development).

## `swoole:debug:service-pools`

Lists the services that `StatefulServicesPass` has replaced with a pooled proxy - the services of
which each coroutine gets an instance of its own instead of sharing one.

```bash
bin/console swoole:debug:service-pools
bin/console swoole:debug:service-pools --filter=twig
```

```console
Swoole service pools
====================

Container instantiation (5)
---------------------------

 One instance per coroutine, built by the container and handed out by DiServicePool.

 * data_collector.twig
 * twig
 * twig.form.engine
 * twig.form.renderer
 * twig.profile

Unmanaged factory instantiation (0)
-----------------------------------

 One instance per coroutine of whatever the factory below builds, handed out by a
 UnmanagedFactoryServicePool per factory method, registered on the first call through
 UnmanagedFactoryInstantiator.

 none
```

The two sections are two different ways of getting an instance, not two kinds of service:

- **Container instantiation** - the pool asks the container for the service. This covers everything
  that reaches the pass by being tagged `kernel.reset` or `swoole_bundle.stateful_service`, by being
  a data collector, by being listed in `swoole.platform.coroutines.stateful_services`, or by being
  one of the services the bundle always pools.
- **Unmanaged factory instantiation** - the service is a factory whose *products* are what need
  pooling, and the container never sees those products at all. The factory is tagged
  `swoole_bundle.unmanaged_factory`, its factory methods are intercepted, and a pool is created per
  factory method the first time it is called.

`--filter` keeps only the ids containing the given string. With coroutines disabled the command
prints a warning and lists nothing, because in that case nothing has been proxified.

The command reads the container the application is actually running, so it works in any environment
and needs no rebuild - which is what makes it usable in production, where `debug:container` has no
dump to read.

## `debug:container` and the service pools

Symfony's `debug:container` shows the pooling too, but only because the bundle makes it: without
`DebugContainerRedumpPass` a debug kernel reports a container in which nothing has been pooled at
all.

The reason is an ordering one. `debug:container` does not read the container that runs; in a debug
kernel it reads the XML dump (and the `.ser` twin beside it) that FrameworkBundle writes to
`%debug.container.dump%` from a `before removing` pass at priority **-255**. `StatefulServicesPass`
runs in the same stage at **-10000** - later, because it has to see the definitions every other pass
has finished with. So the dump was a snapshot of the container from before a single service was
proxified, and `twig` showed up as plain Twig with no pool, no proxy and no wrapped original beside
it. A kernel without debug never had the problem: it has no dump, so
`BuildDebugContainerTrait` rebuilds and recompiles the container instead, which runs
`StatefulServicesPass` along with everything else.

`DebugContainerRedumpPass` runs at **-20000**, below `StatefulServicesPass`, and hands the container
back to FrameworkBundle's own dumper so the file describes what was really compiled. It does nothing
at all when coroutine support is off, since then the first dump was already accurate and a second
one would cost a full `XmlDumper` run for no change.

What that buys you:

```bash
# the proxified original, and the pool built beside it
bin/console debug:container twig.swoole_coop.wrapped
bin/console debug:container twig.swoole_coop.service_pool

# every unmanaged factory, with the arguments the pass resolved for it
bin/console debug:container --tag=swoole_bundle.unmanaged_factory
```

```console
Symfony Container Services Tagged with "swoole_bundle.unmanaged_factory" Tag
===========================================================================

 Service ID                            factoryMethod    returnType              limit  resetter
 App\Service\RepositoryFactory         newInstance      App\InMemoryRepository  15     repository_resetter
 messenger.transport.doctrine.factory  createTransport  DoctrineTransport
```

That tag query is the clearest illustration of the ordering: `messenger.transport.doctrine.factory`
is tagged by `MessengerProcessor` *during* `StatefulServicesPass`, so before the re-dump it was
missing from the list entirely while factories tagged in application config were already there.

Three ids exist per pooled service, and it is worth knowing which is which:

| Id | What it is |
| --- | --- |
| `<id>` | the proxy handed to everything that depends on the service |
| `<id>.swoole_coop.wrapped` | the original definition, made public so the pool can build instances by id |
| `<id>.swoole_coop.service_pool` | the `DiServicePool` holding the per-coroutine instances |

`<id>.swoole_coop.wrapped_factory` is the unmanaged-factory equivalent of the second row. It keeps
the factory's original visibility rather than being made public, so a private factory is listed
among the container's removed ids rather than its service ids.

Use `swoole:debug:service-pools` for the digest of what is pooled, and `debug:container` to drill
into any single entry.

## Long running commands in task workers

`messenger:consume` and friends can be run inside the server's task workers rather than as
containers of their own. That is configuration (`swoole.task_worker.commands`) rather than a command
this bundle registers, and it is **experimental** - see
[Long running commands in task workers](swoole-task-worker-commands.md).
