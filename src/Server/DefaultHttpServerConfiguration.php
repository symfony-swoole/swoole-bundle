<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server;

use Assert\Assertion;
use Assert\AssertionFailedException;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Server\Config\Socket;
use SwooleBundle\SwooleBundle\Server\Config\Sockets;

/**
 * @phpstan-type SwooleSettingsInputShape = array{
 *   daemonize?: bool,
 *   pid_file?: string,
 *   hook_flags?: int,
 *   max_coroutine?: int,
 *   reactor_count?: int,
 *   worker_count?: int,
 *   task_worker_count?: int|string,
 *   serve_static?: string,
 *   public_dir?: string,
 *   buffer_output_size?: string,
 *   package_max_length?: string,
 *   worker_max_request?: int,
 *   worker_max_request_grace?: int,
 *   enable_coroutine?: bool,
 *   task_enable_coroutine?: bool,
 *   task_use_object?: bool,
 *   hook_flags?: int,
 *   log_file?: string,
 *   log_level?: string,
 * }
 * @phpstan-import-type SwooleSettingsShape from HttpServerConfiguration
 * @todo Create interface and split this class
 * @final
 */
final class DefaultHttpServerConfiguration implements HttpServerConfiguration
{
    private const SWOOLE_HTTP_SERVER_CONFIG_DAEMONIZE = 'daemonize';
    private const SWOOLE_HTTP_SERVER_CONFIG_SERVE_STATIC = 'serve_static';
    private const SWOOLE_HTTP_SERVER_CONFIG_REACTOR_COUNT = 'reactor_count';
    private const SWOOLE_HTTP_SERVER_CONFIG_WORKER_COUNT = 'worker_count';
    private const SWOOLE_HTTP_SERVER_CONFIG_TASK_WORKER_COUNT = 'task_worker_count';
    private const SWOOLE_HTTP_SERVER_CONFIG_PUBLIC_DIR = 'public_dir';
    private const SWOOLE_HTTP_SERVER_CONFIG_LOG_FILE = 'log_file';
    private const SWOOLE_HTTP_SERVER_CONFIG_LOG_LEVEL = 'log_level';
    private const SWOOLE_HTTP_SERVER_CONFIG_PID_FILE = 'pid_file';
    private const SWOOLE_HTTP_SERVER_CONFIG_BUFFER_OUTPUT_SIZE = 'buffer_output_size';
    private const SWOOLE_HTTP_SERVER_CONFIG_PACKAGE_MAX_LENGTH = 'package_max_length';
    private const SWOOLE_HTTP_SERVER_CONFIG_WORKER_MAX_REQUEST = 'worker_max_request';
    private const SWOOLE_HTTP_SERVER_CONFIG_WORKER_MAX_REQUEST_GRACE = 'worker_max_request_grace';
    private const SWOOLE_HTTP_SERVER_CONFIG_ENABLE_COROUTINE = 'enable_coroutine';
    private const SWOOLE_HTTP_SERVER_CONFIG_MAX_COROUTINE = 'max_coroutine';
    private const SWOOLE_HTTP_SERVER_CONFIG_TASK_ENABLE_COROUTINE = 'task_enable_coroutine';
    private const SWOOLE_HTTP_SERVER_CONFIG_TASK_USE_OBJECT = 'task_use_object';
    private const SWOOLE_HTTP_SERVER_CONFIG_COROUTINE_HOOK_FLAGS = 'hook_flags';

    /**
     * @todo add more
     * @see https://github.com/swoole/swoole-docs/blob/master/modules/swoole-server/configuration.md
     * @see https://github.com/swoole/swoole-docs/blob/master/modules/swoole-http-server/configuration.md
     */
    private const SWOOLE_HTTP_SERVER_CONFIGURATION = [
        self::SWOOLE_HTTP_SERVER_CONFIG_BUFFER_OUTPUT_SIZE => 'buffer_output_size',
        self::SWOOLE_HTTP_SERVER_CONFIG_COROUTINE_HOOK_FLAGS => 'hook_flags',
        self::SWOOLE_HTTP_SERVER_CONFIG_DAEMONIZE => 'daemonize',
        self::SWOOLE_HTTP_SERVER_CONFIG_ENABLE_COROUTINE => 'enable_coroutine',
        self::SWOOLE_HTTP_SERVER_CONFIG_LOG_FILE => 'log_file',
        self::SWOOLE_HTTP_SERVER_CONFIG_LOG_LEVEL => 'log_level',
        self::SWOOLE_HTTP_SERVER_CONFIG_MAX_COROUTINE => 'max_coroutine',
        self::SWOOLE_HTTP_SERVER_CONFIG_PACKAGE_MAX_LENGTH => 'package_max_length',
        self::SWOOLE_HTTP_SERVER_CONFIG_PID_FILE => 'pid_file',
        self::SWOOLE_HTTP_SERVER_CONFIG_PUBLIC_DIR => 'document_root',
        self::SWOOLE_HTTP_SERVER_CONFIG_REACTOR_COUNT => 'reactor_num',
        self::SWOOLE_HTTP_SERVER_CONFIG_SERVE_STATIC => 'enable_static_handler',
        self::SWOOLE_HTTP_SERVER_CONFIG_TASK_ENABLE_COROUTINE => 'task_enable_coroutine',
        self::SWOOLE_HTTP_SERVER_CONFIG_TASK_USE_OBJECT => 'task_use_object',
        self::SWOOLE_HTTP_SERVER_CONFIG_TASK_WORKER_COUNT => 'task_worker_num',
        self::SWOOLE_HTTP_SERVER_CONFIG_WORKER_COUNT => 'worker_num',
        self::SWOOLE_HTTP_SERVER_CONFIG_WORKER_MAX_REQUEST => 'max_request',
        self::SWOOLE_HTTP_SERVER_CONFIG_WORKER_MAX_REQUEST_GRACE => 'max_request_grace',
    ];

    private const SWOOLE_SERVE_STATIC = [
        'advanced' => false,
        'default' => true,
        'off' => false,
    ];

    private const SWOOLE_LOG_LEVELS = [
        'debug' => SWOOLE_LOG_DEBUG,
        'trace' => SWOOLE_LOG_TRACE,
        'info' => SWOOLE_LOG_INFO,
        'notice' => SWOOLE_LOG_NOTICE,
        'warning' => SWOOLE_LOG_WARNING,
        'error' => SWOOLE_LOG_ERROR,
    ];

    /**
     * @var SwooleSettingsShape
     */
    private array $settings;

    /**
     * @param SwooleSettingsInputShape $settings settings available:
     *                        - reactor_count (default: number of cpu cores)
     *                        - worker_count (default: 2 * number of cpu cores)
     *                        - task_worker_count (default: unset; auto => number of cpu cores; number of task workers)
     *                        - serve_static (default: false)
     *                        - public_dir (default: '%kernel.root_dir%/public')
     *                        - buffer_output_size (default: '2097152' unit in byte (2MB))
     *                        - package_max_length (default: '8388608b' unit in byte (8MB))
     *                        - worker_max_requests: Number of requests after which the worker reloads
     *                        - worker_max_requests_grace: Max random number of requests for worker reloading
     *                        - enable_coroutine: enable coroutines in web processes
     *                        - task_enable_coroutine: enable coroutines in task workers
     *                        - task_use_object: enable OOP style task API
     *                        - hook_flags: coroutine hook flags
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly Swoole $swoole,
        private readonly Sockets $sockets,
        private string $runningMode = 'process',
        array $settings = [],
        private readonly ?int $maxConcurrency = null,
    ) {
        $this->changeRunningMode($runningMode);
        $this->initializeSettings($settings);
    }

    public function isDaemon(): bool
    {
        return isset($this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_DAEMONIZE]);
    }

    public function hasPidFile(): bool
    {
        return isset($this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_PID_FILE]);
    }

    public function servingStaticContent(): bool
    {
        return isset($this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_SERVE_STATIC])
            && $this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_SERVE_STATIC] !== 'off';
    }

    public function hasPublicDir(): bool
    {
        return !empty($this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_PUBLIC_DIR]);
    }

    public function changeServerSocket(Socket $socket): void
    {
        $this->sockets->changeServerSocket($socket);
    }

    public function getSockets(): Sockets
    {
        return $this->sockets;
    }

    public function getMaxConcurrency(): ?int
    {
        return $this->maxConcurrency;
    }

    /**
     * @throws AssertionFailedException
     */
    public function enableServingStaticFiles(string $publicDir): void
    {
        $settings = [
            self::SWOOLE_HTTP_SERVER_CONFIG_PUBLIC_DIR => $publicDir,
        ];

        if ($this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_SERVE_STATIC] === 'off') {
            $settings[self::SWOOLE_HTTP_SERVER_CONFIG_SERVE_STATIC] = 'default';
        }

        $this->setSettings($settings);
    }

    public function isReactorRunningMode(): bool
    {
        return $this->runningMode === 'reactor';
    }

    public function getRunningMode(): string
    {
        return $this->runningMode;
    }

    public function getCoroutinesEnabled(): bool
    {
        return isset($this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_ENABLE_COROUTINE])
            && $this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_ENABLE_COROUTINE];
    }

    /**
     * @throws AssertionFailedException
     */
    public function getPid(): int
    {
        Assertion::true(
            $this->existsPidFile(),
            'Could not get pid file. It does not exists or server is not running in background.'
        );

        /** @var string $contents */
        $contents = file_get_contents($this->getPidFile());
        Assertion::numeric($contents, 'Contents in pid file is not an integer or it is empty');

        return (int) $contents;
    }

    public function existsPidFile(): bool
    {
        return $this->hasPidFile() && file_exists($this->getPidFile());
    }

    /**
     * @throws AssertionFailedException
     */
    public function getPidFile(): string
    {
        Assertion::keyIsset($this->settings, self::SWOOLE_HTTP_SERVER_CONFIG_PID_FILE, 'Setting "%s" is not set.');

        return $this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_PID_FILE];
    }

    public function getWorkerCount(): int
    {
        return $this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_WORKER_COUNT];
    }

    public function getReactorCount(): int
    {
        return $this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_REACTOR_COUNT];
    }

    public function getServerSocket(): Socket
    {
        return $this->sockets->getServerSocket();
    }

    public function getMaxRequest(): int
    {
        return $this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_WORKER_MAX_REQUEST];
    }

    public function getMaxRequestGrace(): ?int
    {
        return $this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_WORKER_MAX_REQUEST_GRACE] ?? null;
    }

    /**
     * @throws AssertionFailedException
     */
    public function getPublicDir(): string
    {
        Assertion::true(
            $this->hasPublicDir(),
            sprintf('Setting "%s" is not set or empty.', self::SWOOLE_HTTP_SERVER_CONFIG_PUBLIC_DIR)
        );

        return $this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_PUBLIC_DIR];
    }

    /**
     * @return SwooleSettingsShape
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Get settings formatted for swoole http server.
     *
     * @return SwooleSettingsShape
     * @see \Swoole\Http\Server::set()
     * @todo create swoole settings transformer
     */
    public function getSwooleSettings(): array
    {
        /** @var SwooleSettingsShape $swooleSettings */
        $swooleSettings = [];

        foreach ($this->settings as $key => $setting) {
            $swooleSettingKey = self::SWOOLE_HTTP_SERVER_CONFIGURATION[$key];
            $swooleGetter = sprintf('getSwoole%s', str_replace('_', '', $swooleSettingKey));
            if (method_exists($this, $swooleGetter)) {
                $setting = $this->{$swooleGetter}();
            }

            if ($setting === null) {
                continue;
            }

            $swooleSettings[$swooleSettingKey] = $setting;
        }

        return $swooleSettings; // @phpstan-ignore-line
    }

    /**
     * @see getSwooleSettings()
     */
    public function getSwooleLogLevel(): int
    {
        return self::SWOOLE_LOG_LEVELS[$this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_LOG_LEVEL]];
    }

    /**
     * @see getSwooleSettings()
     */
    public function getSwooleEnableStaticHandler(): bool
    {
        return self::SWOOLE_SERVE_STATIC[$this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_SERVE_STATIC]];
    }

    /**
     * @see getSwooleSettings()
     */
    public function getSwooleDocumentRoot(): ?string
    {
        return $this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_SERVE_STATIC] === 'default'
            ? $this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_PUBLIC_DIR]
            : null;
    }

    /**
     * @see getSwooleSettings()
     */
    public function getSwooleMaxRequest(): int
    {
        return $this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_WORKER_MAX_REQUEST] ?? 0;
    }

    /**
     * @see getSwooleSettings()
     */
    public function getSwooleMaxRequestGrace(): ?int
    {
        return $this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_WORKER_MAX_REQUEST_GRACE] ?? null;
    }

    /**
     * @throws AssertionFailedException
     */
    public function daemonize(?string $pidFile = null): void
    {
        $settings = [self::SWOOLE_HTTP_SERVER_CONFIG_DAEMONIZE => true];

        if ($pidFile !== null) {
            $settings[self::SWOOLE_HTTP_SERVER_CONFIG_PID_FILE] = $pidFile;
        }

        $this->setSettings($settings);
    }

    public function getTaskWorkerCount(): int
    {
        return $this->settings[self::SWOOLE_HTTP_SERVER_CONFIG_TASK_WORKER_COUNT] ?? 0;
    }

    private function changeRunningMode(string $runningMode): void
    {
        Assertion::inArray($runningMode, ['process', 'reactor', 'thread']);

        $this->runningMode = $runningMode;
    }

    /**
     * @param SwooleSettingsInputShape $init
     * @throws AssertionFailedException
     */
    private function initializeSettings(array $init): void
    {
        $this->settings = []; // @phpstan-ignore-line
        $cpuCores = $this->swoole->cpuCoresCount();

        if (!isset($init[self::SWOOLE_HTTP_SERVER_CONFIG_REACTOR_COUNT])) {
            $init[self::SWOOLE_HTTP_SERVER_CONFIG_REACTOR_COUNT] = $cpuCores;
        }

        if (!isset($init[self::SWOOLE_HTTP_SERVER_CONFIG_WORKER_COUNT])) {
            $init[self::SWOOLE_HTTP_SERVER_CONFIG_WORKER_COUNT] = 2 * $cpuCores;
        }

        if (
            array_key_exists(self::SWOOLE_HTTP_SERVER_CONFIG_TASK_WORKER_COUNT, $init)
            && $init[self::SWOOLE_HTTP_SERVER_CONFIG_TASK_WORKER_COUNT] === 'auto'
        ) {
            $init[self::SWOOLE_HTTP_SERVER_CONFIG_TASK_WORKER_COUNT] = $cpuCores;
        }

        $this->setSettings($init);
    }

    /**
     * @param array<string, mixed> $settings
     * @throws AssertionFailedException
     */
    private function setSettings(array $settings): void
    {
        foreach ($settings as $name => $value) {
            if ($value === null) {
                continue;
            }

            $this->validateSetting($name, $value);
            $this->settings[$name] = $value; // @phpstan-ignore-line
        }

        Assertion::false($this->isDaemon() && !$this->hasPidFile(), 'Pid file is required when using daemon mode');
        Assertion::false(
            $this->servingStaticContent() && !$this->hasPublicDir(),
            'Enabling static files serving requires providing "public_dir" setting.'
        );
    }

    /**
     * @throws AssertionFailedException
     */
    private function validateSetting(string $key, mixed $value): void
    {
        Assertion::keyExists(
            self::SWOOLE_HTTP_SERVER_CONFIGURATION,
            $key,
            'There is no configuration mapping for setting "%s".'
        );

        switch ($key) {
            case self::SWOOLE_HTTP_SERVER_CONFIG_SERVE_STATIC:
                Assertion::inArray($value, array_keys(self::SWOOLE_SERVE_STATIC));

                break;
            case self::SWOOLE_HTTP_SERVER_CONFIG_DAEMONIZE:
            case self::SWOOLE_HTTP_SERVER_CONFIG_TASK_USE_OBJECT:
                Assertion::boolean($value);

                break;
            case self::SWOOLE_HTTP_SERVER_CONFIG_PUBLIC_DIR:
                Assertion::directory($value, 'Public directory does not exists. Tried "%s".');

                break;
            case self::SWOOLE_HTTP_SERVER_CONFIG_LOG_LEVEL:
                Assertion::inArray($value, array_keys(self::SWOOLE_LOG_LEVELS));

                break;
            case self::SWOOLE_HTTP_SERVER_CONFIG_PACKAGE_MAX_LENGTH:
                Assertion::integer($value, sprintf('Setting "%s" must be an integer.', $key));
                Assertion::greaterThan(
                    $value,
                    0,
                    'Package max length value cannot be negative or zero, "%s" provided.'
                );

                break;
            case self::SWOOLE_HTTP_SERVER_CONFIG_BUFFER_OUTPUT_SIZE:
                Assertion::integer($value, sprintf('Setting "%s" must be an integer.', $key));
                Assertion::greaterThan(
                    $value,
                    0,
                    'Buffer output size value cannot be negative or zero, "%s" provided.'
                );

                break;
            case self::SWOOLE_HTTP_SERVER_CONFIG_TASK_WORKER_COUNT:
            case self::SWOOLE_HTTP_SERVER_CONFIG_REACTOR_COUNT:
            case self::SWOOLE_HTTP_SERVER_CONFIG_WORKER_COUNT:
                Assertion::integer($value, sprintf('Setting "%s" must be an integer.', $key));
                Assertion::greaterThan($value, 0, 'Count value cannot be negative, "%s" provided.');

                break;
            case self::SWOOLE_HTTP_SERVER_CONFIG_WORKER_MAX_REQUEST:
                Assertion::integer($value, sprintf('Setting "%s" must be an integer.', $key));
                Assertion::greaterOrEqualThan($value, 0, 'Value cannot be negative, "%s" provided.');

                break;
            case self::SWOOLE_HTTP_SERVER_CONFIG_WORKER_MAX_REQUEST_GRACE:
                Assertion::nullOrInteger($value, sprintf('Setting "%s" must be an integer or null.', $key));
                Assertion::nullOrGreaterOrEqualThan($value, 0, 'Value cannot be negative, "%s" provided.');

                break;
            case self::SWOOLE_HTTP_SERVER_CONFIG_ENABLE_COROUTINE:
            case self::SWOOLE_HTTP_SERVER_CONFIG_TASK_ENABLE_COROUTINE:
                Assertion::boolean($value, sprintf('Setting "%s" must be a boolean.', $key));

                break;
            case self::SWOOLE_HTTP_SERVER_CONFIG_COROUTINE_HOOK_FLAGS:
                Assertion::integer($value, sprintf('Setting "%s" must be a positive integer.', $key));
                Assertion::greaterOrEqualThan($value, 0, sprintf('Setting "%s" must be a positive integer.', $key));

                break;
            case self::SWOOLE_HTTP_SERVER_CONFIG_MAX_COROUTINE:
                Assertion::integer(
                    $value,
                    sprintf('Setting "%s" must be a positive integer lower or equal than 100000.', $key)
                );
                Assertion::between(
                    $value,
                    0,
                    100000,
                    sprintf('Setting "%s" must be a positive integer lower or equal than 100000.', $key)
                );
                // no break
            default:
                return;
        }
    }
}
