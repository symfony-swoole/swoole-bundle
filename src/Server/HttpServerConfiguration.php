<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server;

use Assert\AssertionFailedException;
use SwooleBundle\SwooleBundle\Server\Config\Socket;
use SwooleBundle\SwooleBundle\Server\Config\Sockets;

/**
 * @phpstan-type SwooleSettingsShape = array{
 *   daemonize?: bool,
 *   pid_file: string,
 *   hook_flags?: int,
 *   max_coroutine: int,
 *   reactor_count: int,
 *   worker_count: int,
 *   task_worker_count?: int,
 *   serve_static: string,
 *   public_dir: string,
 *   upload_tmp_dir: string,
 *   buffer_output_size?: string,
 *   package_max_length?: string,
 *   worker_max_request: int,
 *   worker_max_request_grace?: int,
 *   enable_coroutine?: bool,
 *   task_enable_coroutine?: bool,
 *   task_use_object?: bool,
 *   hook_flags?: int,
 *   log_file: string,
 *   log_level: string,
 *   user?: string,
 *   group?: string,
 * }
 */
interface HttpServerConfiguration
{
    public function isDaemon(): bool;

    public function hasPidFile(): bool;

    public function servingStaticContent(): bool;

    public function hasPublicDir(): bool;

    public function changeServerSocket(Socket $socket): void;

    public function getSockets(): Sockets;

    public function getMaxConcurrency(): ?int;

    /**
     * @throws AssertionFailedException
     */
    public function enableServingStaticFiles(string $publicDir): void;

    public function isReactorRunningMode(): bool;

    public function getRunningMode(): string;

    public function getCoroutinesEnabled(): bool;

    public function getUser(): string;

    public function getGroup(): string;

    /**
     * @throws AssertionFailedException
     */
    public function getPid(): int;

    public function existsPidFile(): bool;

    /**
     * @throws AssertionFailedException
     */
    public function getPidFile(): string;

    public function getWorkerCount(): int;

    public function getReactorCount(): int;

    public function getServerSocket(): Socket;

    public function getMaxRequest(): int;

    public function getMaxRequestGrace(): ?int;

    /**
     * @throws AssertionFailedException
     */
    public function getPublicDir(): string;

    /**
     * @throws AssertionFailedException
     */
    public function getUploadTmpDir(): string;

    /**
     * @return SwooleSettingsShape
     */
    public function getSettings(): array;

    /**
     * Get settings formatted for swoole http server.
     *
     * @see \Swoole\Http\Server::set()
     * @todo create swoole settings transformer
     * @return SwooleSettingsShape
     */
    public function getSwooleSettings(): array;

    /**
     * @see getSwooleSettings()
     */
    public function getSwooleLogLevel(): int;

    /**
     * @see getSwooleSettings()
     */
    public function getSwooleEnableStaticHandler(): bool;

    /**
     * @see getSwooleSettings()
     */
    public function getSwooleDocumentRoot(): ?string;

    /**
     * @see getSwooleSettings()
     */
    public function getSwooleMaxRequest(): int;

    /**
     * @see getSwooleSettings()
     */
    public function getSwooleMaxRequestGrace(): ?int;

    /**
     * @throws AssertionFailedException
     */
    public function daemonize(?string $pidFile = null): void;

    public function getTaskWorkerCount(): int;
}
