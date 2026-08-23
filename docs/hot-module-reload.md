# Hot module reload

> **ALPHA.** The modes, the configuration keys and the messages on this page can change without a
> major release.

A Swoole worker keeps its memory between requests. This makes the server fast. It also means that a
file you edit has no effect: the worker still runs the code it loaded when it started.

Hot module reload (HMR) helps with part of this problem. A timer in each worker watches the
application files. When a file changes, the workers reload, and the next request runs the new code.

HMR cannot apply every change. This is not a bug. It follows from the way the server starts. Most of
this page explains which changes work, which do not, and what happens with the ones that do not.

```yaml
swoole:
  http_server:
    hmr:
      enabled: auto   # off | auto | inotify | stat | external
```

HMR runs in debug mode only. If `kernel.debug` is false, the bundle ignores `hmr.enabled` completely.

| mode | what it does |
|---|---|
| `off` | watches nothing |
| `auto` | uses `inotify` if the extension is loaded, otherwise `stat`. This is the default |
| `inotify` | the `inotify` extension reports changes in the watched files |
| `stat` | PHP reads the mtime of each watched file. Use this where inotify does not work, for example Docker bind mounts and macOS |
| `external` | watches nothing. It writes the files loaded before the fork to `nonReloadableFiles.txt` and `nonReloadableAppFiles.txt` in `%swoole_bundle.cache_dir%`, for a script outside the server to act on |

The timer runs every two seconds. It runs in HTTP workers only. A task worker that runs
[long running commands](swoole-task-worker-commands.md) has nothing to reload.

## Which changes a reload can apply

`Server::reload()` creates the workers again from the memory of the master process. So the important
question is not "did the file change?". The important question is **"had the master already loaded
this class when it created the workers?"**

- **A class that no worker has loaded yet.** For example a controller on a route that nobody has
  called, or a service that is created only when it is used. The new worker reads this class from
  disk. This is the case that HMR handles well.
- **A class that the master loaded during kernel boot.** For example the kernel, the bundles, the
  compiler passes, and every service class that boot touched. The new worker inherits this class from
  the master. PHP cannot declare a class twice, so the worker keeps the old code until it stops. Every
  watcher leaves these files out of its list for this reason.
- **Any file that the container was built from.** For example config files, routes and environment
  variables. PHP never includes these files, so no watcher looks at them. The container is also built
  during the kernel boot in the master, before `Server::start()`. A reloaded worker never boots a
  kernel, so it never checks the container and never builds a new one. Deleting the cache directory
  while the server runs changes nothing, because no process reads it again.

In practice: if your application creates most of its services during boot, HMR helps very little. If
your change is in `config/`, HMR cannot help at all.

## HMR reports the changes it cannot apply

This used to fail silently. You saved a config file, the workers reloaded, and nothing changed. The
reload closed every open connection and kept the old container. Nothing explained why your change had
no effect.

Now HMR checks first whether a reload can help. `RestartAwareHotModuleReloader` wraps the configured
watcher. Before each tick, it asks a list of `RestartCondition` objects. If one of them reports a
problem, HMR logs it and does not reload:

```
[warning] Hot module reload is paused because the compiled container no longer matches the files it
was built from, and the kernel is booted before the workers fork, so no reloaded worker ever compiles
a new one. Reloading the workers cannot apply this, so the server has to be restarted - run it under
"swoole:server:watch" to have that done for you.
```

Each worker logs this once, not once per tick. Nothing changes until you restart the server, so one
message every two seconds would only hide the first one.

### The two conditions

#### `ContainerFreshness`

This condition checks whether the compiled container still matches the files it was built from.

Symfony already stores this information. It writes the container through a `ConfigCache` and creates
a `.meta` file next to it. The `.meta` file lists every resource that the container was built from.
The bundle reads this file directly and checks the resources on each tick. This is more exact than
watching directories and guessing which files matter.

The bundle does not call `ConfigCache::isFresh()`, although this looks like the obvious solution. In
debug mode, that method uses a checker that stores every answer in a private static array. The key is
the resource plus the timestamp of the cache. Neither value changes while the server runs. So the
first answer, given when everything was still fresh, is returned for the whole life of the process.
Symfony expects this checker to run once per process during boot. That is true for normal
applications, but not for a server that stays up.

Inside a worker, this condition finds changes in config files and globs, because it compares mtimes.
It does not find changes in `ReflectionClassResource` entries. Those entries check freshness by
reading the class from the current process. A worker still holds the old class, so they always report
"fresh", even after you edit the file. In the fixture application of this bundle, 164 of the 234
resources are of this type.

The condition stays silent when it cannot answer. A container that was built with debug off has no
`.meta` file. In that case, reporting every server as stale would not help anybody.

#### `NonReloadableCodeFreshness`

This condition checks whether any code that the workers cannot reload has changed since the server
started. It covers the classes that `ContainerFreshness` cannot see from inside a worker.

It creates its baseline in the master process, with `get_included_files()`. Only the master gives the
correct answer, because the list must contain the files that were loaded *before* the fork.

It ignores the vendor directory and the cache directory. In the fixture application, this removes
1317 of 1564 files. Nobody edits those files during development. Watching them would make a cheap
check expensive, because the server would read thousands of files every two seconds.

The condition compares content, not only mtime. An mtime only tells you that a file was written. It
does not tell you that the content changed. Git checkouts, editors and build steps often write the
same bytes again. So mtime and size are only a first, cheap test. If they change, the condition
compares an `xxh128` hash of the content. This way, a warning always means a real change.

## Applying the other changes with `swoole:server:watch`

The warning tells you that a restart is needed. `swoole:server:watch` performs the restart. It is
important to understand that this command is **not** part of the server. It is the parent process of
the server:

```
php bin/console swoole:server:watch      <- a normal console command that supervises
  php bin/console swoole:server:run      <- the swoole master, a separate OS process
    manager
      workers
```

The command reads `src` and `config` once per second. You can change these paths with `--path`. It
runs `php -l` on every changed file, so an unfinished file cannot stop the server. Then it sends
SIGTERM to the master and starts a completely new `swoole:server:run`. The new process has a new
master, a new kernel boot and new workers. Nothing is kept from the old process. This is why it can
apply the changes that a reload cannot.

Before it starts the new process, it deletes the cache directory, but only if the change affected the
container. It uses the same `ContainerFreshness` as the HMR warning. It does this only when needed,
because a new container costs time on the next boot. Most restarts happen after a change inside a
method, and the container does not depend on that.

The command does not leave this check to the kernel, although a debug kernel does check its container
on boot and rebuilds it when it is stale. That check covers the container only. The service pools and
the warmed caches next to it have no such check, so the server would reuse them. With debug off there
is no check at all, because a `ConfigCache` without debug treats every existing file as fresh. A
restarted server in production mode would then keep the old container.

See [console commands](console-commands.md#swooleserverwatch) for the available options.

## Using both together

The two features complete each other. In a Docker development setup, you usually want the supervisor
as well:

```yaml
swoole:
  http_server:
    hmr:
      enabled: auto
```

```dockerfile
CMD ["bin/console", "swoole:server:watch"]
```

HMR is the fast path for a change in a class that is loaded late. It only reloads the workers and does
not boot a kernel. The supervisor is the safe path for every change, but it costs a full restart.

If you use both, remember that they overlap. By default the supervisor watches `src` and `config`. So
a change under `src` first reloads the workers, and about one second later the supervisor restarts the
whole server anyway. Set `--path=config` if you want HMR to stay the fast path for your `src` files.

## Cost

The HMR timer is a repeating timer on the reactor of the worker. A worker can only exit when its
reactor has no events left. A worker that still holds this timer therefore waits for `max_wait_time`
and is then stopped by force. It does not exit as soon as it becomes idle.

This costs three seconds per worker with the Swoole default. Where `worker_max_wait_time` is higher,
for example for slow task worker commands, it costs that value instead. The server paid this on every
stop and every reload until the timer was cleared in `onWorkerExit`. Measured restarts with HMR on are
now as fast as restarts without it.
