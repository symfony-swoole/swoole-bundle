# Step debugging a swoole server

EXPERIMENTAL.

Xdebug cannot start a session on a swoole server by itself. Everything on this page exists because of
one fact, so it is worth stating before the configuration:

**Xdebug decides whether to open a session when the PHP script starts, and under swoole the script is
the worker process, not the request.** A worker forks, boots the kernel, and then serves requests or
tasks for hours without PHP ever starting again.

That has two consequences, and both look like bugs elsewhere:

- `xdebug.start_with_request=trigger` and the `XDEBUG_SESSION` cookie it looks for are evaluated once
  per worker, at fork time, when there is no request and no cookie. A cookie sent afterwards can never
  reach them. The server runs perfectly and cannot be stepped through.
- `xdebug.start_with_request=yes` attaches the **master**, before it has forked anything. With an IDE
  listening the master is held in that session and the server never finishes starting: one process at
  0% CPU with an established connection to the debug port, and every request answered `502` by
  whatever sits in front of it. With no IDE listening the attach fails, the server starts, and
  starting the IDE afterwards debugs nothing - the one attempt those workers were going to make is
  already spent.

So the bundle attaches from PHP instead, with `xdebug_connect_to_client()`, at moments it chooses:
inside a worker, after the fork, when there is something to debug.

## Setting it up

Leave xdebug's own auto-start off - it has nothing useful to do here and `yes` is actively harmful:

```ini
xdebug.mode = debug
xdebug.start_with_request = no
xdebug.start_upon_error = no
```

`start_upon_error` matters more than it looks. It defaults to `yes` in some distributions, and booting
a Symfony kernel raises deprecations - every one of them counts as an error, and each error opens a
session. Hundreds of connection attempts in a row is the difference between a server that starts in
seconds and one that never starts at all.

Then choose where sessions are opened:

```php
'platform' => [
    'xdebug' => [
        'enabled' => true,       // registers the handlers; default
        'requests' => 'trigger', // 'off' | 'trigger' | 'always'; default 'trigger'
        'workers' => false,      // attach every worker as it starts
        'tasks' => false,        // attach a task worker when a task arrives
    ],
],
```

The handlers are registered whenever the `platform` node is present, which it is for any application
using coroutines. Set `enabled: false` to opt out.

## The three attach points

| Setting | Attaches | Reaches |
|---|---|---|
| `requests` | the http worker serving a request | controllers, and everything a request runs |
| `workers` | every worker, http and task, as it starts | boot, and code no request reaches |
| `tasks` | a task worker when a task arrives | message handlers over the task transport |

**`requests: 'trigger'`** is the default and the one to reach for. The request has to ask, by carrying
`XDEBUG_SESSION` or `XDEBUG_TRIGGER` as a cookie or a query parameter - which is what a browser
debugging extension sets, and what `?XDEBUG_TRIGGER=1` gives you from curl. Requiring it is not
politeness: attaching unconditionally puts every request through a connect to an IDE that is usually
not listening, and while `xdebug.connect_timeout_ms` bounds each attempt, paying it on every asset of
every page is the difference between a dev server and a slow one. `always` drops the requirement.

**`workers: true`** is the blunt one, and the only thing that reaches code no request runs: message
handlers, projections, the long running commands a task worker hosts, and the kernel boot itself. It
attaches unconditionally, so every worker of every kind opens a connection as it starts and pays the
connect timeout where nothing is listening. It also runs again for each replacement worker, which is
what makes a debugger survive a reload or a worker recycled on `--memory-limit`.

**`tasks: true`** is the cheaper half of that. An idle task worker never connects; the session is
opened in the worker that is about to run the handler, on the first task it receives, and stays open
afterwards. There is no trigger and no way to build one - a task carries the application's payload,
not headers or cookies, so nothing can carry a request's intent across.

## Where they sit

`requests` decorates the request handler at the top of the chain, outside every other handler
including the one that establishes the coroutine context. A session opened there is open for
everything that follows without exception, so a breakpoint can go anywhere a request reaches - the
application's code, and this bundle's own handlers on the way to it.

`workers` decorates the outermost worker start handler, so the session is open before any other start
handler runs, including the one that hands a task worker its long running command and never returns.

`tasks` decorates inside the handler that resets services between tasks and outside the rest, so a
breakpoint sees the task the way the application will.

## Fewer workers while debugging

Worth doing, and not something the bundle can do for you: with several http workers, which one serves
a request is swoole's choice, so a breakpoint hits in whichever it lands in. Setting
`http_server.settings.worker_count` to `1` while debugging makes that deterministic. It also cuts how
many processes ask your IDE for a session at once - PhpStorm accepts one at a time unless
*Max. simultaneous connections* is raised, and queued sessions are workers that are not working.

## Tests

Each attach point has a feature test, and each has its own environment - `xdebug_requests`,
`xdebug_workers`, `xdebug_tasks` - with only that one enabled. That separation is forced rather than
tidy: a client that has attached reports itself attached, so whichever handler gets there first makes
every later one a no-op in that process. With `workers` on, an http worker is already attached before
it ever sees a request, and the request handler would never do anything to observe.

The tests do not attach for real. That would need the extension loaded in the container running the
server *and* something listening on the debug port, neither of which a suite can assume - and neither
is what the handlers decide. `XdebugClient` is an interface for exactly this reason: the fixtures put
a recording implementation behind the handlers, which is the whole of what they talk to.

## When nothing stops at a breakpoint

The handlers are guarded at runtime, never at compile time: whether the extension is loaded is a
property of the process, and the compiled container outlives it. A container built while xdebug was
off is reused unchanged by a process that has it on, which is exactly what switching the debugger on
by recreating a container does. So a stale container is never the reason a breakpoint is missed.

What to check instead, in order:

1. Is the extension loaded in the process serving the request? `php -m | grep xdebug` proves it for the
   CLI, not for the server - those are the same ini, but only if the server was restarted afterwards.
2. Did the request ask? With `requests: 'trigger'`, no cookie means no session, by design.
3. Set `xdebug.log` and read it. It records every attempt, and says plainly whether it connected,
   whether the IDE detached, and why. `Could not connect` means nothing was listening;
   `Debug client detached: No external connection found` means your IDE accepted the socket and then
   refused the session, which is usually a missing server configuration or path mapping on its side.
