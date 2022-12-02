<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Symfony\Bundle\DependencyInjection;

use function K911\Swoole\decode_string_as_set;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

final class Configuration implements ConfigurationInterface
{
    public const DEFAULT_PUBLIC_DIR = '%kernel.project_dir%/public';

    private const CONFIG_NAME = 'swoole';

    private $builder;

    public function __construct(TreeBuilder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $this->builder->getRootNode()
            ->children()
                ->arrayNode('http_server')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('host')
                            ->cannotBeEmpty()
                            ->defaultValue('0.0.0.0')
                        ->end()
                        ->scalarNode('port')
                            ->cannotBeEmpty()
                            ->defaultValue(9501)
                        ->end()
                        ->arrayNode('trusted_hosts')
                            ->defaultValue([])
                            ->prototype('scalar')->end()
                            ->beforeNormalization()
                                ->ifString()
                                ->then(fn ($v): array => decode_string_as_set($v))
                            ->end()
                        ->end()
                        ->arrayNode('trusted_proxies')
                            ->defaultValue([])
                            ->prototype('scalar')->end()
                            ->beforeNormalization()
                                ->ifString()
                                ->then(fn ($v): array => decode_string_as_set($v))
                            ->end()
                        ->end()
                        ->enumNode('running_mode')
                            ->cannotBeEmpty()
                            ->defaultValue('process')
                            ->values(['process', 'reactor', 'thread'])
                        ->end()
                        ->enumNode('socket_type')
                            ->cannotBeEmpty()
                            ->defaultValue('tcp')
                            ->values(['tcp', 'tcp_ipv6', 'udp', 'udp_ipv6', 'unix_dgram', 'unix_stream'])
                        ->end()
                        ->booleanNode('ssl_enabled')
                            ->defaultFalse()
                            ->treatNullLike(false)
                        ->end()
                        ->enumNode('hmr')
                            ->cannotBeEmpty()
                            ->defaultValue('auto')
                            ->treatFalseLike('off')
                            ->values(['off', 'auto', 'inotify'])
                        ->end()
                        ->arrayNode('api')
                            ->addDefaultsIfNotSet()
                            ->beforeNormalization()
                                ->ifTrue(fn ($v): bool => \is_string($v) || \is_bool($v) || is_numeric($v) || null === $v)
                                ->then(function ($v): array {
                                    return [
                                        'enabled' => (bool) $v,
                                        'host' => '0.0.0.0',
                                        'port' => 9200,
                                    ];
                                })
                            ->end()
                            ->children()
                                ->booleanNode('enabled')
                                    ->defaultFalse()
                                ->end()
                                ->scalarNode('host')
                                    ->cannotBeEmpty()
                                    ->defaultValue('0.0.0.0')
                                ->end()
                                ->scalarNode('port')
                                    ->cannotBeEmpty()
                                    ->defaultValue(9200)
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('static')
                            ->addDefaultsIfNotSet()
                            ->beforeNormalization()
                                ->ifString()
                                ->then(function ($v): array {
                                    return [
                                        'strategy' => $v,
                                        'public_dir' => 'off' === $v ? null : self::DEFAULT_PUBLIC_DIR,
                                        'mime_types' => [],
                                    ];
                                })
                            ->end()
                            ->children()
                                ->enumNode('strategy')
                                    ->defaultValue('auto')
                                    ->treatFalseLike('off')
                                    ->values(['off', 'default', 'advanced', 'auto'])
                                ->end()
                                ->scalarNode('public_dir')
                                    ->defaultValue(self::DEFAULT_PUBLIC_DIR)
                                ->end()
                                ->variableNode('mime_types')
                                    ->info('File extensions to mime types map.')
                                    ->defaultValue([])
                                    ->validate()
                                        ->always(function ($mimeTypes) {
                                            $validValues = [];

                                            foreach ((array) $mimeTypes as $extension => $mimeType) {
                                                $extension = trim((string) $extension);
                                                $mimeType = trim((string) $mimeType);

                                                if ('' === $extension || '' === $mimeType) {
                                                    throw new InvalidTypeException(sprintf('Invalid mime type %s for file extension %s.', $mimeType, $extension));
                                                }

                                                $validValues[$extension] = $mimeType;
                                            }

                                            return $validValues;
                                        })
                                    ->end()
                                ->end() // end mime types
                            ->end()
                        ->end() // end static
                        ->arrayNode('exception_handler')
                            ->addDefaultsIfNotSet()
                            ->beforeNormalization()
                                ->ifString()
                                ->then(function ($v): array {
                                    return [
                                        'type' => $v,
                                        'verbosity' => 'auto',
                                        'handler_id' => null,
                                    ];
                                })
                            ->end()
                            ->children()
                                ->enumNode('type')
                                    ->cannotBeEmpty()
                                    ->defaultValue('auto')
                                    ->treatFalseLike('auto')
                                    ->values(['json', 'production', 'symfony', 'custom', 'auto'])
                                ->end()
                                ->enumNode('verbosity')
                                    ->cannotBeEmpty()
                                    ->defaultValue('auto')
                                    ->treatFalseLike('auto')
                                    ->values(['trace', 'verbose', 'default', 'auto'])
                                ->end()
                                ->scalarNode('handler_id')
                                    ->defaultNull()
                                ->end()
                            ->end()
                        ->end() // end exception_handler
                        ->arrayNode('services')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('debug_handler')
                                    ->defaultNull()
                                    ->setDeprecated(
                                        'k911/swoole-bundle',
                                        '0.11',
                                        'The "%node%" option is deprecated. It is no longer needed to provide debug http kernel.'
                                    )
                                ->end()
                                ->booleanNode('trust_all_proxies_handler')
                                    ->defaultFalse()
                                    ->treatNullLike(false)
                                ->end()
                                ->booleanNode('cloudfront_proto_header_handler')
                                    ->defaultFalse()
                                    ->treatNullLike(false)
                                ->end()
                                ->booleanNode('entity_manager_handler')
                                    ->defaultNull()
                                ->end()
                                ->booleanNode('blackfire_profiler')
                                    ->defaultNull()
                                ->end()
                                ->arrayNode('tideways_apm')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->booleanNode('enabled')
                                            ->defaultFalse()
                                            ->treatNullLike(false)
                                        ->end()
                                        ->scalarNode('service_name')
                                            ->validate()
                                                ->always(fn (string $serviceName): string => trim($serviceName))
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end() // drivers
                        ->arrayNode('settings')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('log_file')
                                    // TODO: NEXT MAJOR - remove default value
                                    ->defaultValue('%kernel.logs_dir%/swoole_%kernel.environment%.log')
                                ->end()
                                ->enumNode('log_level')
                                    ->cannotBeEmpty()
                                    ->values(['auto', 'debug', 'trace', 'info', 'notice', 'warning', 'error'])
                                    ->defaultValue('auto')
                                ->end()
                                ->scalarNode('pid_file')
                                    ->defaultNull()
                                ->end()
                                ->scalarNode('buffer_output_size')
                                    ->defaultValue(2097152)
                                ->end()
                                ->scalarNode('package_max_length')
                                    ->defaultValue(8388608)
                                ->end()
                                ->scalarNode('worker_count')
                                    ->defaultValue(1)
                                ->end()
                                ->scalarNode('reactor_count')
                                    ->defaultValue(1)
                                ->end()
                                ->scalarNode('worker_max_request')
                                    ->defaultValue(0)
                                ->end()
                                ->scalarNode('worker_max_request_grace')
                                    ->defaultNull()
                                ->end()
                            ->end()
                        ->end() // settings
                    ->end()
                ->end() // server
                ->arrayNode('task_worker')
                    ->children()
                        ->arrayNode('services')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('reset_handler')
                                    ->defaultTrue()
                                    ->treatNullLike(false)
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('settings')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('worker_count')
                                    ->defaultNull()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end() // task_worker
                ->arrayNode('platform')
                    ->children()
                        ->arrayNode('coroutines')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->defaultFalse()
                                ->end()
                                ->scalarNode('max_coroutines')
                                    ->defaultValue(100000) // swoole default
                                    ->validate()
                                        ->always(function (?int $max): ?int {
                                            if (null === $max) {
                                                return $max;
                                            }

                                            if ($max < 1 || $max > 100000) {
                                                throw new InvalidConfigurationException(sprintf('Max coroutines %d should be between 1 and 100000.', $max));
                                            }

                                            return $max;
                                        })
                                    ->end()
                                ->end()
                                    ->scalarNode('max_concurrency')
                                    ->defaultNull()
                                    ->validate()
                                        ->always(function (?int $max): ?int {
                                            if (null === $max) {
                                                return $max;
                                            }

                                            if ($max < 1 || $max > 100000) {
                                                throw new InvalidConfigurationException(sprintf('Max concurrency %d should be between 1 and 100000.', $max));
                                            }

                                            return $max;
                                        })
                                    ->end()
                                ->end()
                                ->scalarNode('max_service_instances')
                                    ->defaultNull()
                                    ->validate()
                                    ->always(function (?int $max): ?int {
                                        if (null === $max) {
                                            return $max;
                                        }

                                        if ($max < 1 || $max > 100000) {
                                            throw new InvalidConfigurationException(sprintf('Max service instances (%d) should be between 1 and 100000.', $max));
                                        }

                                        return $max;
                                    })
                                    ->end()
                                ->end()
                                ->arrayNode('stateful_services')
                                    ->scalarPrototype()
                                    ->beforeNormalization()
                                        ->ifString()
                                            ->then(fn (string $v): string => trim($v))
                                        ->end()
                                    ->end()
                                ->end()
                                ->arrayNode('compile_processors')
                                    ->arrayPrototype()
                                        ->beforeNormalization()
                                            ->ifString()
                                                ->then(fn (string $v): array => ['class' => $v])
                                            ->end()
                                        ->children()
                                            ->scalarNode('class')
                                                ->cannotBeEmpty()
                                            ->end()
                                            ->integerNode('priority')
                                                ->defaultValue(0)
                                            ->end()
                                            ->arrayNode('config')
                                                ->ignoreExtraKeys()
                                                ->variablePrototype()->end()
                                                ->beforeNormalization()
                                                    ->castToArray()
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                                ->arrayNode('doctrine_processor_config')
                                    ->children()
                                        ->integerNode('global_limit')
                                            ->defaultNull()
                                            ->min(1)
                                            ->max(200)
                                        ->end()
                                        ->arrayNode('limits')
                                            ->ignoreExtraKeys()
                                            ->integerPrototype()->end()
                                            ->beforeNormalization()
                                                ->castToArray()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end() // end coroutines
                    ->end()
                ->end() // platform
            ->end()
        ;

        return $this->builder;
    }

    public static function fromTreeBuilder(): self
    {
        $treeBuilderClass = TreeBuilder::class;

        return new self(new $treeBuilderClass(self::CONFIG_NAME));
    }
}
