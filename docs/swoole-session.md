# Swoole Session Storage

Shared memory session storage backed by an OpenSwoole Table. Because the Table is allocated in the master process before Swoole forks workers, all workers share the same session data without any IPC overhead.

## Configuration

Two configuration files are required.

**`config/packages/swoole.yaml`** — table capacity:

```yaml
swoole:
    session:
        max_data_bytes: 4096
        max_active_sessions: 1024
```

| Option | Default | Description |
| --- | --- | --- |
| `max_data_bytes` | 4096 | Maximum size (in bytes) of serialized session data per record |
| `max_active_sessions` | 1024 | Maximum number of concurrent session records in the table (rounded up to the next power of 2 by OpenSwoole) |

**`config/packages/framework.yaml`** — tell Symfony to use this storage:

```yaml
framework:
    session:
        storage_factory_id: swoole_bundle.session.table_storage_factory
        cookie_lifetime: 3600
```

## Using a Custom Session Handler

In addition to the default OpenSwoole Table storage, the bundle can persist sessions through any PHP `\SessionHandlerInterface` implementation (for example Symfony's `PdoSessionHandler`). This is useful when you need sessions to survive server restarts or to share them with non-Swoole parts of the application.

To enable this, keep `storage_factory_id` pointed at the bundle's factory **and** set Symfony's `handler_id` to a service implementing `\SessionHandlerInterface`:

```yaml
# config/packages/swoole.yaml
swoole:
    session:
        max_data_bytes: 4096
        max_active_sessions: 1024

# config/packages/framework.yaml
framework:
    session:
        storage_factory_id: swoole_bundle.session.table_storage_factory
        handler_id: App\Session\PdoSessionHandler
        cookie_lifetime: 3600
```

When both options are configured together, the bundle replaces the default `Storage` implementation with a `SessionHandlerInterfaceStorage` adapter that delegates read/write operations to the configured handler.

### Keeping the handler's PDO connection alive

A worker keeps the session handler's PDO connection for as long as it lives; a database does not keep an idle connection that long. MySQL closes one after its `wait_timeout` - eight hours by default, a quiet night - and a restart of the database closes them all. The worker's next request then fails with `SQLSTATE[HY000]: General error: 2006 MySQL server has gone away`, and so does every request after it.

A connection made by the bundle's factory is looked after the way Doctrine's connections are:

```php
use SwooleBundle\SwooleBundle\Bridge\Symfony\HttpFoundation\Session\PdoSession\PdoConnectionFactory;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

$services->set('app.session.pdo', PDO::class)
    ->factory([PdoConnectionFactory::class, 'newInstanceFromDsnOrUrl'])
    ->arg('$dsnOrUrl', env('DATABASE_URL')->resolve())
    ->tag('swoole_bundle.stateful_service'); // with coroutines: a connection a coroutine

$services->set(PdoSessionHandler::class)
    ->arg('$pdoOrDsn', service('app.session.pdo'));
```

Each such service gets an alive keeper (`PingingPDOAliveKeeper`, `swoole_bundle.session.pdo.alive_keeper.<service id>`): it asks the server `SELECT 1`, and where there is no answer constructs the connection again in place, with what it was constructed with - PDO cannot reconnect, and the object has to stay the one the handler holds. Never inside a transaction. The reconnect is logged at `info`.

When it is asked depends on how the server runs:

- **With coroutines**, as a pooled connection is handed to a coroutine, before the coroutine uses it: the bundle sets an `initializer` on the service's `swoole_bundle.stateful_service` tag (`PDOKeepAliveInitializer`), as it does for Doctrine's connections. A tag that names an initializer of its own is left as it is.
- **Without coroutines**, as a request starts, by [swoole-bundle/resetter-bundle](https://github.com/symfony-swoole/resetter-bundle): the connections are given to a `ConnectionsHandler` of that bundle (`PDOPlatformAliveKeeper`), among its request cycle's initializers, beside the one it has for Doctrine. Only a connection that is already open is asked: a request that uses no session opens none. Without that bundle installed nothing looks after the connection - as nothing looks after Doctrine's.

To look after a connection another way, implement `PDOAliveKeeper` and decorate the service above. A connection you construct yourself (`new PDO(...)`), and not with the factory, is yours to look after.

### Limitations

Using a generic `\SessionHandlerInterface` imposes some restrictions compared to the native OpenSwoole Table storage:

- **No per-call TTL.** `SessionHandlerInterface::write()` has no TTL parameter, so the `ttl` passed by the bundle is ignored. Expiry is controlled by the underlying handler (for example `PdoSessionHandler`'s `ttl` option or PHP's `session.gc_maxlifetime` ini setting).
- **No proactive expiry regeneration.** The adapter cannot distinguish a missing session from an expired one, so it never invokes the `$expired` callback and will not regenerate expired sessions automatically.
- **No `count()` support.** Calling `count()` on the storage throws a `LogicException` because `\SessionHandlerInterface` provides no counting capability.
- **Locking is held per Storage call, not per request.** The adapter opens and closes the handler around each individual `get` / `set` / `delete` operation. Any locking strategy in the handler (for example `PdoSessionHandler::LOCK_TRANSACTIONAL`) is released between those calls, so it does not protect a full read-then-write session cycle.

## How It Works

The Swoole Table is created (via `Swoole\Table::create()`) in the **master process** during container boot, before Swoole forks workers. Workers then inherit the pre-allocated shared memory via `fork()`, giving every worker direct access to the same table without any IPC.

This eager allocation is enforced by the DI layer:

1. **`SwooleTableStorageConfiguratorPass`** (a `CompilerPass`) inspects the `framework.session.storage_factory_id` value. When it is set to `swoole_bundle.session.table_storage_factory`, the pass adds the `swoole_bundle.server_configurator` tag to `WithSwooleTableStorageConfigurator`.
2. **`WithSwooleTableStorageConfigurator`** is a no-op configurator. Its only purpose is to hold a reference to `SwooleTableStorage`, which forces the container to instantiate the storage — and therefore call `Table::create()` — before the server starts.

## Garbage Collection

GC values are read from Symfony session options (`gc_probability` and `gc_divisor` under `framework.session`) or fall back to the PHP ini settings `session.gc_probability` / `session.gc_divisor`.

On each session `save()` call, GC runs probabilistically:

```
random_int(1, gcDivisor) <= gcProbability
```

- Default PHP ini values: `gc_probability=1`, `gc_divisor=100` → 1 % chance per save.
- Setting `gc_probability` to `0` disables GC entirely; expired records remain until overwritten.

## Limits and Constraints

- Session key must not exceed **63 bytes** (OpenSwoole Table key limit).
- Session data must not exceed **`max_data_bytes`**.
- **`max_active_sessions`** is rounded up to the next power of 2 by OpenSwoole internally.
- Table size is **fixed at server start**; it cannot grow dynamically.

## Internal Architecture

| Component | Role |
| --- | --- |
| `SwooleTableStorage` | Wraps `Swoole\Table`; provides `get()`, `set()`, `delete()`, `garbageCollect()`; created via `fromDefaults()` factory in DI |
| `SwooleSessionStorage` | Implements Symfony `SessionStorageInterface`; runs probabilistic GC on `save()` |
| `SwooleSessionStorageFactory` | Creates a `SwooleSessionStorage` per request with the correct lifetime and GC settings |
| `WithSwooleTableStorageConfigurator` | No-op configurator; forces eager DI instantiation of `SwooleTableStorage` in the master process before workers fork |
| `SwooleTableStorageConfiguratorPass` | `CompilerPass`; adds `swoole_bundle.server_configurator` tag to `WithSwooleTableStorageConfigurator` only when `storage_factory_id` is `swoole_bundle.session.table_storage_factory` |