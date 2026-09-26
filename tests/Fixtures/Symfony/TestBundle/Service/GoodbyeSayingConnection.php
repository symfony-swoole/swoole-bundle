<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Service;

use Swoole\Coroutine;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * A connection that is closed politely, in its destructor, the way a mail transport says QUIT and waits for the
 * 221 - which is what makes where it is destroyed matter.
 *
 * The wait is the point: with the runtime hooks on, a socket opened inside a coroutine can only wait inside one.
 * Nothing answers the QUIT, so the read waits out its timeout, and the file says which coroutine it waited in -
 * or the process dies with "API must be called in the coroutine" before writing it, when the instance outlived
 * the scheduler.
 *
 * Pooled (config/task_worker_pool_drain), so the instance the command uses belongs to the pool, not the command.
 */
final class GoodbyeSayingConnection
{
    private const int READ_TIMEOUT_SECONDS = 1;

    /**
     * @var resource|null
     */
    private $connection;

    /**
     * @var resource|null
     */
    private $peer;

    public function __destruct()
    {
        if ($this->connection === null) {
            return;
        }

        fwrite($this->connection, "QUIT\r\n");
        fgets($this->connection);
        file_put_contents(self::filePath(), sprintf("closed in coroutine %d\n", Coroutine::getCid()), FILE_APPEND);

        if ($this->peer === null) {
            return;
        }

        fclose($this->peer);
    }

    public static function filePath(): string
    {
        return ServerTestCase::FIXTURE_RESOURCES_DIR . DIRECTORY_SEPARATOR . 'pool-drain-goodbye.txt';
    }

    public function open(): void
    {
        if ($this->connection !== null) {
            return;
        }

        $server = stream_socket_server('tcp://127.0.0.1:0');
        assert(is_resource($server));
        $connection = stream_socket_client('tcp://' . stream_socket_get_name($server, false));
        assert(is_resource($connection));
        $peer = stream_socket_accept($server);
        assert(is_resource($peer));
        stream_set_blocking($connection, true);
        stream_set_timeout($connection, self::READ_TIMEOUT_SECONDS);

        $this->connection = $connection;
        $this->peer = $peer;
    }
}
