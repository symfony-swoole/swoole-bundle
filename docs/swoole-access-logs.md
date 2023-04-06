# Swoole Server monolog integration - access logs

This bundle contains access log formatter and mapper to handle and format symfony request and symfony response.
Log format is configurable in a same way as apache mod log: http://httpd.apache.org/docs/2.4/mod/mod_log_config.html#examples

Event is disabled by default, and access log is created/logged in kernel terminate event.

## How to enable/configure access logs?

1. Enable `access_log` section in `swoole.yaml` and configure `format` if needed.
2. Optional you can enable autoconfiguration of monolog line formatter service and use in monolog handler configuration with settings `register_monolog_formatter_service`.
3. Override/reconfigure handler/channel in `packages/monolog.yaml` and exclude `swoole.access_log` channel from other handlers if needed, all depends on your configuration.

```yaml
  # config/services.yaml

  # service is auto registered if option `register_monolog_formatter_service` is set to true`
  # service id can be overridden with settings `monolog_formatter_service_name` also line formatter $format argument by `monolog_formatter_format`
  monolog.formatter.line.swoole.access_log:
    class: Monolog\Formatter\LineFormatter
    arguments:
      $format: "%%message%% %%context%% %%extra%%\n"
``` 

Example of monolog configuration:
```yaml
  # config/packages/monolog.yaml
  monolog:
    channels: [ 'swoole.access_log' ]
    handlers:
      swoole_access_log:
        type: stream
        path: "php://stderr"
        level: info
        channels: 'swoole.access_log'
        formatter: monolog.formatter.line.swoole.access_log
```

You can also enable monolog processors for channel `swoole.access_log`:

```yaml
  # config/services.yaml
  Monolog\Processor\MemoryPeakUsageProcessor:
    tags:
      - { name: monolog.processor, channel: 'swoole.access_log' }
``` 