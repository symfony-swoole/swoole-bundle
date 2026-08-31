# Worker, coroutine and command on every log record

A swoole server writes one log from many processes at once, and inside each of them from many coroutines
at once. The lines of one request, one message or one activity therefore arrive in the file interleaved
with everybody else's, and nothing on a line says which of the writers it belongs to.

Turning this on adds three fields to the `extra` of every monolog record:

| field     | what it says                                                                | absent when                            |
|-----------|-----------------------------------------------------------------------------|----------------------------------------|
| `worker`  | `web-0` … `web-N` for http workers, `task-0` … `task-N` for task workers     | the process is not a worker of a server |
| `cid`     | the coroutine the line was written in                                        | there is no coroutine                   |
| `command` | the console command the line belongs to                                      | nothing above it is a command           |

A field with nothing to say is left out rather than written empty, so a line from a plain console run
does not carry three nulls explaining that it is not from a server.

```yaml
swoole:
  platform:
    logging:
      worker_context: true
```

Off by default. Everything here lands in `extra`, which the default line formatter prints, so turning it
on unasked would change what every line of an existing application's log looks like.

With monolog's `LineFormatter` a record then reads:

```
[2026-08-31T14:56:28+00:00] app.WARNING: Something happened. [] {"worker":"task-0","cid":8,"command":"messenger:consume default"}
```

## Why not a log file per worker

That was the obvious alternative and it answers less.

A task worker runs a whole **group** of commands side by side - two messenger consumers, say, or two
projection runners differing only in a `--group` - so a file per worker still cannot say which of them
wrote a line. It also cannot follow one request through the interleaving inside a worker, and it leaves
an aggregator with several files to stitch back together. Three fields in `extra` are what a log shipper
already knows how to index.

## How the command is found

The command line is kept in the **coroutine's own context**, not in a field on a service. A field would
be the commands of a group overwriting each other - which is wrong on its own, and fatal under fiber
context checking, where two coroutines writing one property is an error by definition.

A coroutine does not inherit its parent's context, so a lookup walks **up the parent chain** until it
finds a command or runs out. That is what covers the coroutines a command spawns rather than runs in,
which is where nearly all the real work happens: a consumer handling a message in a coroutine of its own
is still `messenger:consume`. The walk is bounded, because a chain that somehow looped would otherwise
take the worker with it.

Two cases have no coroutine to be kept in, and get a field instead:

- a task worker running its command **with coroutines off**, where there is no scheduler to spawn into;
- a command run from a supervisor rather than from the server - `bin/console messenger:consume` - where
  `console.command` fires before anything has started.

Both are safe, because a process outside a coroutine is running exactly one command. The field is
forgotten when the process becomes a worker: every worker is forked from a master running
`swoole:server:run`, and nothing a worker goes on to do belongs to that command.

What a task worker runs is recorded **whole**, as configured, so `--group=open_banking` is on the line
that distinguishes two otherwise identical runners. A command run from the console records its **name
only**: a command line typed at a shell can carry anything - a token in an argument, a path someone
would rather not publish - and it would end up on every line that command writes.

## Reading it back

`worker` numbering follows swoole's own: task workers are numbered straight on from the http ones, so
with four http workers, worker 4 is the first task worker and is labelled `task-0`. That is the same
arithmetic the bundle uses to decide which group of commands a task worker runs, so `task-1` is always
the second group of `swoole.task_worker.commands`.

## See also

- [Long running console commands in task workers](swoole-task-worker-commands.md)
- [Configuration reference](configuration-reference.md)
