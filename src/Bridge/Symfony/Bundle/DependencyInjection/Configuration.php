<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\DependencyInjection;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

use function SwooleBundle\SwooleBundle\decode_string_as_set;

final class Configuration implements ConfigurationInterface
{
    public const DEFAULT_PUBLIC_DIR = '%kernel.project_dir%/public';

    private const CONFIG_NAME = 'swoole';

    public function __construct(private readonly TreeBuilder $builder) {}

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
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
                        ->variableNode('trusted_hosts')
                            ->defaultValue([])
                            ->beforeNormalization()
                                ->ifString()
                                ->then(static fn($v): array => decode_string_as_set($v))
                            ->end()
                        ->end()
                        ->variableNode('trusted_proxies')
                            ->defaultValue([])
                            ->beforeNormalization()
                                ->ifString()
                                ->then(static fn($v): array => decode_string_as_set($v))
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
                        ->arrayNode('hmr')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->enumNode('enabled')
                                    ->cannotBeEmpty()
                                    ->defaultValue('auto')
                                    ->treatFalseLike('off')
                                    ->values(['off', 'auto', 'inotify', 'external'])
                                ->end()
                                ->scalarNode('file_path')
                                    ->defaultValue('%swoole_bundle.cache_dir%')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('api')
                            ->addDefaultsIfNotSet()
                            ->beforeNormalization()
                                ->ifTrue(
                                    static fn($v): bool => is_string($v) || is_bool($v) || is_numeric($v) || $v === null
                                )
                                ->then(static fn($v): array => [
                                    'enabled' => (bool) $v,
                                    'host' => '0.0.0.0',
                                    'port' => 9200,
                                ])
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
                                ->then(static fn($v): array => [
                                    'mime_types' => [],
                                    'public_dir' => $v === 'off' ? null : self::DEFAULT_PUBLIC_DIR,
                                    'strategy' => $v,
                                ])
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
                                        ->always(static function ($mimeTypes) {
                                            $validValues = [];

                                            foreach ((array) $mimeTypes as $extension => $mimeType) {
                                                $extension = trim((string) $extension);
                                                $mimeType = trim((string) $mimeType);

                                                if ($extension === '' || $mimeType === '') {
                                                    throw new InvalidTypeException(
                                                        sprintf(
                                                            'Invalid mime type %s for file extension %s.',
                                                            $mimeType,
                                                            $extension
                                                        )
                                                    );
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
                                ->then(static fn($v): array => [
                                    'handler_id' => null,
                                    'type' => $v,
                                    'verbosity' => 'auto',
                                ])
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
                                        'The "%node%" option is deprecated. '
                                        . 'It is no longer needed to provide debug http kernel.'
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
                                ->booleanNode('blackfire_profiler')
                                    ->defaultFalse()
                                    ->treatNullLike(false)
                                ->end()
                                ->booleanNode('blackfire_monitoring')
                                    ->defaultFalse()
                                    ->treatNullLike(false)
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
                                                ->always(static fn(string $serviceName): string => trim($serviceName))
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                                ->arrayNode('access_log')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->booleanNode('enabled')
                                            ->defaultFalse()
                                            ->treatNullLike(false)
                                        ->end()
                                        ->scalarNode('format')
                                            ->defaultNull()
                                        ->end()
                                        ->scalarNode('register_monolog_formatter_service')
                                            ->defaultNull()
                                            ->treatNullLike(false)
                                        ->end()
                                        ->scalarNode('monolog_formatter_service_name')
                                            ->defaultNull()
                                        ->end()
                                        ->scalarNode('monolog_formatter_format')
                                            ->defaultNull()
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
                                    ->defaultValue(2_097_152)
                                ->end()
                                ->scalarNode('package_max_length')
                                    ->defaultValue(8_388_608)
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
                                ->scalarNode('upload_tmp_dir')
                                    ->defaultValue('/tmp')
                                ->end()
                                ->scalarNode('user')
                                ->end()
                                ->scalarNode('group')
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
                                        ->always(static function (?int $max): ?int {
                                            if ($max === null) {
                                                return $max;
                                            }

                                            if ($max < 1 || $max > 100000) {
                                                throw new InvalidConfigurationException(
                                                    sprintf('Max coroutines %d should be between 1 and 100000.', $max)
                                                );
                                            }

                                            return $max;
                                        })
                                    ->end()
                                ->end()
                                    ->scalarNode('max_concurrency')
                                    ->defaultNull()
                                    ->validate()
                                        ->always(static function (?int $max): ?int {
                                            if ($max === null) {
                                                return $max;
                                            }

                                            if ($max < 1 || $max > 100000) {
                                                throw new InvalidConfigurationException(
                                                    sprintf('Max concurrency %d should be between 1 and 100000.', $max)
                                                );
                                            }

                                            return $max;
                                        })
                                    ->end()
                                ->end()
                                ->scalarNode('max_service_instances')
                                    ->defaultNull()
                                    ->validate()
                                    ->always(static function (?int $max): ?int {
                                        if ($max === null) {
                                            return $max;
                                        }

                                        if ($max < 1 || $max > 100000) {
                                            throw new InvalidConfigurationException(
                                                sprintf(
                                                    'Max service instances (%d) should be between 1 and 100000.',
                                                    $max
                                                )
                                            );
                                        }

                                        return $max;
                                    })
                                    ->end()
                                ->end()
                                ->arrayNode('stateful_services')
                                    ->scalarPrototype()
                                    ->beforeNormalization()
                                        ->ifString()
                                            ->then(static fn(string $v): string => trim($v))
                                        ->end()
                                    ->end()
                                ->end()
                                ->arrayNode('compile_processors')
                                    ->arrayPrototype()
                                        ->beforeNormalization()
                                            ->ifString()
                                                ->then(static fn(string $v): array => ['class' => $v])
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
            ->end();

        return $this->builder;
    }

    public static function fromTreeBuilder(): self
    {
        return new self(new TreeBuilder(self::CONFIG_NAME));
    }
}
