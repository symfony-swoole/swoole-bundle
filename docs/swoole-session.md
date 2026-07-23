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