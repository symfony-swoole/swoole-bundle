# Swoole Bundle Configuration

Documentation of available configuration parameters. See also symfony [bundle configuration](./../src/Bridge/Symfony/Bundle/DependencyInjection/Configuration.php) file or [swoole documentation](https://github.com/swoole/swoole-docs/tree/master/modules).

- [Swoole Bundle Configuration](#swoole-bundle-configuration)
  - [HTTP Server](#http-server)

## HTTP Server

*Example*:

```yaml
swoole:
    http_server:
        port: 9501
        host: 0.0.0.0
        running_mode: process
        socket_type: tcp
        ssl_enabled: false
        trusted_hosts: localhost,127.0.0.1
        trusted_proxies:
            - '*'
            - 127.0.0.1/8
            - 192.168.2./16

        # enables static file serving
        static: advanced
        # equals to:
        # ---
        # static:
        #     public_dir: '%kernel.project_dir%/public'
        #     strategy: advanced
        #     mime_types: ~
        # ---
        # strategy can be one of: (default) auto, off, advanced, default
        #   - off: turn off feature
        #   - auto: use 'advanced' when debug enabled or not production environment
        #   - advanced: use request handler class \K911\Swoole\Server\RequestHandler\AdvancedStaticFilesServer
        #   - default: use default swoole static serving (faster than advanced, but supports less content types)
        # ---
        # mime types registration by file extension for static files serving in format: 'file extension': 'mime type'
        # this only works when 'static' strategy is set to 'advanced'
        #
        #   mime_types:
        #       '*': 'text/plain' # fallback override
        #       customFileExtension: 'custom-mime/type-name'
        #       sqlite: 'application/x-sqlite3'

        # enables hot module reload using inotify
        coroutines_support: 
          enabled: false
            # default false. when enabled, swoole coroutine hooks for IO apis get activated
            # (https://www.swoole.co.uk/docs/modules/swoole-coroutine-enableCoroutine) and all stateful services
            # are being used in contextual way with multiple instances for each service
            # (https://www.swoole.co.uk/article/isolating-variables-with-coroutine-context)
          stateful_services:
            - SomeStatefulServiceId
              # add stateful service ids which need to be proxified and the bundle cannot detect them alone
              # check the section below about coroutines usage
          compile_processors:
            - class: ProcessorClass1
              priority: 10
            - ProcessorClass2 # default priority
              # register classes implementing the CompileProcessor interface
              # check the section below about coroutines usage
        hmr: auto
        # hmr can be one of: off, (default) auto, inotify
        #   - off: turn off feature
        #   - auto: use inotify if installed in the system
        #   - inotify: use inotify

        # enables api server on specific port
        # by default it is disabled (can be also enabled using --api flag via cli)
        api: true
        # equals to:
        # ---
        # api:
        #     enabled: true
        #     host: 0.0.0.0
        #     port: 9200

        # additional swoole symfony bundle services
        services:

            # see: \K911\Swoole\Bridge\Symfony\HttpFoundation\TrustAllProxiesRequestHandler
            trust_all_proxies_handler: true

            # see: \K911\Swoole\Bridge\Symfony\HttpFoundation\CloudFrontRequestFactory
            cloudfront_proto_header_handler: true

            # see: \K911\Swoole\Bridge\Upscale\Blackfire\WithProfiler
            blackfire_profiler: false

            # see: \K911\Swoole\Bridge\Tideways\Apm\WithApm
            tideways_apm:
                enabled: true
                service_name: 'app_name' # service name for Tideways APM UI

        # swoole http server settings
        # see https://www.swoole.co.uk/docs/modules/swoole-server/configuration
        settings:
            reactor_count: 2
            worker_count: 4
            # when not set, swoole sets these are automatically set based on count of host CPU cores
            task_worker_count: 2 # one of: positive number, "auto", or null to disable creation of task worker processes (default: null)

            log_level: auto
            # can be one of: (default) auto, debug, trace, info, notice, warning, error
            #   - auto: when debug set to debug, when not set to notice
            #   - {debug,trace,info,notice,warning,error}: see swoole configuration

            log_file: '%kernel.logs_dir%/swoole_%kernel.environment%.log'
            pid_file: /var/run/swoole_http_server.pid

            buffer_output_size: 2097152
            # in bytes, 2097152b = 2 MiB

            package_max_length: 8388608
            # in bytes, 8388608b = 8 MiB

            worker_max_request: 0
            # integer >= 0, indicates the number of requests after which a worker reloads automatically
            # This can be useful to limit memory leaks
            worker_max_request_grace: ~
            # 'grace period' for worker reloading. If not set, default is worker_max_request / 2. Worker reloads
            # after 'worker_max_request + rand(0,worker_max_request_grace)' requests
```

## Additional info for coroutines usage

**WARNING!!! Coroutines usage in Symfony is highly experimental at this stage. It still needs to be tested properly.
There may be changes in the configuration/implementation in later releases.**

For now, coroutines are only supported in the web server. It is possible that they would also work in task workers,
but no one has ever tested this approach.

To be able to use coroutines in a Symfony app, it is mandatory to change all stateful services of the app into special
proxies, which preserve separate state for each coroutine context. This bundle has a Proxifier service, which modifies
the Symfony container for each stateful service. The proxification phase happens during application compiling
(in a compiler pass).

There are some generally known services registered, which need to be proxified. Additionally, all services implementing
the `Symfony\Contracts\Service\ResetInterface` are proxified as well. For convenience it is also possible to register
stateful services to be processed by the bundle using 3 ways:
 - using `coroutines_support.stateful_services` configuration option
 - using compile processors
 - using the `swoole_bundle.stateful_service` service tag

**ANOTHER WARNING!!! This feature is dependent on the PHP FFI extension and a custom fork of `lisachenko/z-engine`,
The original package seems to [not be maintained anymore](https://github.com/lisachenko/z-engine/issues/55).
Feel free to reconsider usage of coroutines.**

The Z-Engine library is used to remove the `final` flag from classes in PHP runtime, because to make a proxy
for a class, tha class cannot be final by design. This is a working solution for final classes from 3rd party
libraries. Other solutions are considered, but none of them seems to be better than this one. Any better ideas
are appreciated.

To work as intended, please use `pixelfederation/z-engine`.

**YET ANOTHER WARNING!!! This feature is also dependent on a not yet released version of `laminas/laminas-code`,
This bundle contains a custom override for `Code\Generator\ValueGenerator.php`, which is 
[waiting to be merged](https://github.com/laminas/laminas-code/pull/143).
There should be no issues with the changes in the file, the custom version only extends the features of the original file.
It should be compatible with most of the `laminas/laminas-code` versions installed as a dependency
of `ocramius/proxy-manager`.**

### Proxification

To be able to run a Symfony app with Swoole coroutines without modifications of the app, this bundle implements
a technique, that identifies services critical for concurrent access (typically resettable services with request
scoped data) and enhances them to be safe for concurrent workload. It is done wia proxification this bundle
wraps the services to special proxies using the DI container. These proxies behave exactly as the same instance
types as the wrapped services, but under the hood, the proxies have a separate service instance for each coroutine.
This helps to hide and separate contextually scoped coroutine data under one service instance. With this technique, 
it is possible to achieve a correct non-blocking workloads with coroutines within Symfony apps without 
any code modification.

### Compile processors

Compile processors are project/other-bundle level extensions where it is possible to setup proxification
of custom services unknown to the proxification mechanism in this bundle. The intention is to have a programmatic
way to proxify chosen services or to add special tags to services ina a dynamic way.

All compile processors have to implement the interface 
`K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor`.
Compile processors run in the proxification phase of the `StatefulServicePass` compiler pass at the end of application
compilation. 

Two services are accessible in the compile processor:
- Symfony container builder (you can use it the same way as in casual compiler passes)
- proxifier from this bundle (you can use it to proxify any service directly)

This is an example compile processor:

```php
<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\TestBundle\DependencyInjection\CompilerPass;

use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\CompileProcessor;
use K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection\CompilerPass\StatefulServices\Proxifier;
use K911\Swoole\Tests\Fixtures\Symfony\TestBundle\Service\SleepingCounter;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SleepingCounterCompileProcessor implements CompileProcessor
{
    public function process(ContainerBuilder $container, Proxifier $proxifier): void
    {
        // this line can be achieved by registering FQCN to the coroutines_support.stateful_services configuration
        $proxifier->proxifyService(SleepingCounter::class);
    }
}
```

### Special tags

This bundle provides 5 special tags for marking some services to be handled specially, so they can safely run 
in multiple coroutine contexts:

- `swoole_bundle.stateful_service` - use to mark a service to be proxified
- `swoole_bundle.decorated_stateful_service` - use to mark a decorated service to be proxified. (the bundle needs to guess
  the decorated service in the decorated chain, which needs to be proxified, and there may be changes in this feature).
- `swoole_bundle.safe_stateful_service` - use to mark a resettable service as safe for coroutine usage, so it doesn't need to be proxified
- `swoole_bundle.unmanaged_factory` - use to mark services which create other stateful services (-> factories) outside of the SF container context, 
  e.g. in runtime (the point is to proxify those services in runtime)
- `swoole_bundle.stability_checker` - use this tag to mark a service as a stability checker

### Stability checkers

Stability checkers are services services which make run-time checks for paired stateful services. They check,
if the paired service is stable and is able to be used for the next request (e.g. it can check if the entity manager is still open).
A stability checker usually doesn't have to be tagged manually, autoconfiguration will tag all services implementing
the `K911\Swoole\Bridge\Symfony\Container\StabilityChecker` interface automatically.

An example of a stability checker:

```php
<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Doctrine\ORM;

use Doctrine\ORM\EntityManager;
use K911\Swoole\Bridge\Symfony\Container\StabilityChecker;
use UnexpectedValueException;

final class EntityManagerStabilityChecker implements StabilityChecker
{
    public function isStable(object $service): bool
    {
        if (!$service instanceof EntityManager) {
            throw new UnexpectedValueException(\sprintf('Invalid service - expected %s, got %s', EntityManager::class, \get_class($service)));
        }

        return $service->isOpen();
    }

    public static function getSupportedClass(): string
    {
        return EntityManager::class;
    }
}
```

When the checker returns false, the coroutine engine will forget the unstable service and will fetch a fresh instance
from the DI container. Otherwise the engine will mark the service instance as unused and will assign it to a coroutine 
in the next web request.