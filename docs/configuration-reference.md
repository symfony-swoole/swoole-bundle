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
    # see https://openswoole.com/docs/modules/swoole-server/configuration
    settings:
      reactor_count: 2
      worker_count: 4
      # when not set, swoole sets these are automatically set based on count of host CPU cores

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
      
  task_worker: # task workers' specific settings
      services:
        reset_handler: true # default true, set to false to disable services resetter on task processing end
      settings:
        worker_count: 2 # one of: positive number, "auto", or null to disable creation of task worker processes (default: null)
  platform:
    coroutines:
      enabled: false
      # default false. when enabled, swoole coroutine hooks for IO apis get activated
      # (https://openswoole.com/docs/modules/swoole-coroutine-enableCoroutine) and all stateful services
      # are being used in contextual way with multiple instances for each service
      # (https://openswoole.com/article/isolating-variables-with-coroutine-context)
      max_coroutines: 100
      # default value is 100000, if not set
      # (https://openswoole.com/docs/modules/swoole-server/configuration#max_coroutine)
      max_concurrency: 100
      # default value is null, if not set
      # it helps limitting this number, so you can be sure, that there is a limited amount of proxified service instances
      # (https://openswoole.com/article/v4-7-1-released)
      max_service_instances: 100
      # default is the same as max_concurrency, if not set, or max_coroutines if max_concurrency is not set
      # it is important to limit the amount of stateful service instances, that need to be created,
      # otherwise there would be at least so many service instances as coroutines, which might be quite enough
      # regarding how much coroutines can be run concurrently (e.g. 100000)
      # this number is by default equal to max_concurrency or max_coroutines, since it doesn't make sense to have 
      # more stateful services than coroutines, but sometimes it might have sense to have less instances (e.g. if max_coroutines is too high)
      stateful_services:
        - SomeStatefulServiceId
          # add stateful service ids which need to be proxified and the bundle cannot detect them alone
          # check the section below about coroutines usage
      compile_processors:
        - class: ProcessorClass1
          priority: 10
          config: [] # all data will be propagated to the processor constructor, this attribute is not needed
        - ProcessorClass2 # default priority
          # register classes implementing the CompileProcessor interface
          # check the section below about coroutines usage
      # configuration options for doctrine processor - set instance limits for each connection type, or global limit
      doctrine_processor_config:
        # max connections in each swoole process for each doctrine connections, default is max_service_instances
        global_limit: 10 
        limits:
          # connection with name 'default' will have max 9 instances per swoole process, if not set, default is global_limit
          default: 9
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

### Application kernel modification

To be able to use coroutines in your application the following trait has to be used in the application kernel class:
`K911\Swoole\Bridge\Symfony\Kernel\CoroutinesSupportingKernelTrait`. 

This trait will disable state resetting of the app kernel while cloning it and makes some default overrides 
and initializations.

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

### Resetters

This bundle overrides Symfony resetting mechanism (`kernel.reset` tag), because the original mechanism is not well suited
for concurrent request processing. The main problem is that Symfony service resetter resets each already initialised 
service on each request, which would lock a service instance of each service for each coroutine serving a request.
But not all of the services may be needed to be available for the request. Such behaviour might produce unnecessary
slowdowns while handling requests, since there are srvice instance limits implemented into the bundle. That means
that requests that don't need specific services would need to wait for other requests (which may or may not need 
the same service instances as the waiting/blocked request), until they release the specific service instance.

This behaviour is problematic, so this bundle activates the resetting mechanism on each service instance only when 
it is used for the first time in the request (while assigning it for concrete coroutine in the service pool). When
the request does not need the specific service, it won't get assigned to the coroutine. This helps the app to serve requests
without unnecessary blocking, e.g. when there are liveliness probes in the app that do not touch any database,
there is no need to assign a database connection for them. Since database connections (and maybe some other services) 
are expensive resources, they will be used on demand, not always.

By default, this bundle automatically extracts all the resetter methods from Symfony service resetter and assigns
them to the service pools for each resettable service. That means, there is no need to do anything special in the app.

Sometimes there are special cases that emerge for using Symfony with coroutines turned on, like pinging DBAL connections
before the first query on each request (because the connections may be already closed, btw this bundle already has
a solution for this, using connection pingers 
from [pixelfederation/doctrine-resettable-em-bundle](https://github.com/pixelfederation/doctrine-resettable-em-bundle)).

For special cases like this, you can implement a custom service resetter. The resetter has to implement
the `K911\Swoole\Bridge\Symfony\Container\Resetter` interface and has to be registered in the SF container
as a service. After that, you can configure any stateful service to use the resetter just by adding the resetter
service id to the stateful service tag like this:

```yaml
services:
  my_custom_resetter:
    class: My\Custom\ResetterClass

  some_stateful_service:
    class: My\Stateful\ServiceClass
    tags: [{ name: swoole_bundle.stateful_service, resetter: my_custom_resetter, reset_on_each_request: true}]

  some_unmanaged_factory:
    class: My\Unmanaged\FactoryClass2
    tags: [{ name: swoole_bundle.unmanaged_factory, resetter: my_custom_resetter}]
```

By default, each resetted service is being reset on first usage during request. But there are some services, 
that need to be reset on each request. To activate this, use the `reset_on_each_request` attribute.

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