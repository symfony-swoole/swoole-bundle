<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Monolog;

use Assert\Assertion;
use InvalidArgumentException;
use LogicException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Utils;
use SwooleBundle\SwooleBundle\Component\Locking\Mutex;
use UnexpectedValueException;

/*
 * This is an override of the original class from Monolog
 * It makes the error handler method public, so there are no exceptions thrown if the error handler calls it
 * from outside the class.
 * Also, there is a mutex for setting and restoring error handlers, just to be sure, that nothing breaks.
 */
final class StreamHandler extends AbstractProcessingHandler
{
    protected const int MAX_CHUNK_SIZE = 2_147_483_647;

    /** 10MB */
    protected const DEFAULT_CHUNK_SIZE = 10 * 1024 * 1024;

    protected int $streamChunkSize;

    /**
     * @var resource|null
     */
    protected $stream;
    protected string|null $url = null;

    /**
     * @var true|null
     */
    private bool|null $dirCreated = null;

    private Mutex $mutex;

    /**
     * @param resource|string $stream If a missing path can't be created, an UnexpectedValueException will be
     * thrown on first write
     * @param int|null $filePermission Optional file permissions (default (0644) are only for owner read/write)
     * @param bool $useLocking Try to lock log file before doing any writes
     * @throws InvalidArgumentException If stream is not a resource or string
     */
    public function __construct(
        $stream,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
        protected int|null $filePermission = null,
        protected bool $useLocking = false,
    ) {
        parent::__construct($level, $bubble);

        if (($phpMemoryLimit = Utils::expandIniShorthandBytes(ini_get('memory_limit'))) !== false) {
            if ($phpMemoryLimit > 0) {
                // use max 10% of allowed memory for the chunk size, and at least 100KB
                $this->streamChunkSize = min(self::MAX_CHUNK_SIZE, max((int) ($phpMemoryLimit / 10), 100 * 1024));
            } else {
                // memory is unlimited, set to the default 10MB
                $this->streamChunkSize = self::DEFAULT_CHUNK_SIZE;
            }
        } else {
            // no memory limit information, set to the default 10MB
            $this->streamChunkSize = self::DEFAULT_CHUNK_SIZE;
        }

        if (is_resource($stream)) {
            $this->stream = $stream;

            stream_set_chunk_size($this->stream, $this->streamChunkSize);
        } elseif (is_string($stream)) {
            $this->url = Utils::canonicalizePath($stream);
        } else {
            throw new InvalidArgumentException('A stream must either be a resource or a string.');
        }
    }

    public function close(): void
    {
        if ($this->url !== null && is_resource($this->stream)) {
            fclose($this->stream);
            error_log(sprintf('[%d] Closing stream for %s', Co::getCid(), $this->url));
        }
        $this->stream = null;
        $this->dirCreated = null;
    }

    /**
     * Return the currently active stream if it is open.
     *
     * @return resource|null
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Return the stream URL if it was configured with a URL and not an active resource.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getStreamChunkSize(): int
    {
        return $this->streamChunkSize;
    }

    public function setMutex(Mutex $mutex): void
    {
        $this->mutex = $mutex;
    }

    /**
     * this callback will ensure that in each coroutine only the correct stream handler instance will get
     * the error message.
     */
    public function customErrorHandler(?string &$errorMessage): callable
    {
        return function (int $code, string $msg) use (&$errorMessage): bool {
            $errorMessage = preg_replace('{^(fopen|mkdir)\(.*?\): }', '', $msg);

            return true;
        };
    }

    protected function write(LogRecord $record): void
    {
        if (!is_resource($this->stream)) {
            $url = $this->url;
            if ($url === null || $url === '') {
                throw new LogicException(
                    'Missing stream url, the stream can not be opened. This may be caused by a premature call to close().' . Utils::getRecordMessageForException(
                        $record
                    )
                );
            }
            $this->createDir($url);
            $errorMessage = null;

            try {
                $this->mutex->acquire();
                set_error_handler($this->customErrorHandler($errorMessage));
                $stream = fopen($url, 'a');
                if ($this->filePermission !== null) {
                    @chmod($url, $this->filePermission);
                }
            } finally {
                restore_error_handler();
                $this->mutex->release();
            }

            if (!is_resource($stream)) {
                $this->stream = null;

                throw new UnexpectedValueException(
                    sprintf(
                        'The stream or file "%s" could not be opened in append mode: ' . $errorMessage,
                        $url
                    ) . Utils::getRecordMessageForException(
                        $record
                    )
                );
            }
            stream_set_chunk_size($stream, $this->streamChunkSize);
            $this->stream = $stream;
        }

        $stream = $this->stream;
        if ($this->useLocking) {
            // ignoring errors here, there's not much we can do about them
            flock($stream, LOCK_EX);
        }

        $this->streamWrite($stream, $record);

        if (!$this->useLocking) {
            return;
        }

        flock($stream, LOCK_UN);
    }

    /**
     * Write to stream.
     *
     * @param resource $stream
     */
    protected function streamWrite($stream, LogRecord $record): void
    {
        if (!is_resource($stream)) {
            error_log('[ERROR] Stream is not a valid resource!');

            return;
        }

        Assertion::string($record->formatted);

        @fwrite($stream, (string) $record->formatted);
    }

    private function getDirFromStream(string $stream): ?string
    {
        $pos = strpos($stream, '://');
        if ($pos === false) {
            return dirname($stream);
        }

        if (str_starts_with($stream, 'file://')) {
            return dirname(substr($stream, 7));
        }

        return null;
    }

    private function createDir(string $url): void
    {
        // Do not try to create dir if it has already been tried.
        if ($this->dirCreated === true) {
            return;
        }

        $dir = $this->getDirFromStream($url);
        if ($dir !== null && !is_dir($dir)) {
            $errorMessage = null;

            try {
                $this->mutex->acquire();
                set_error_handler($this->customErrorHandler($errorMessage));
                $status = mkdir($dir, 0o777, true);
            } finally {
                restore_error_handler();
                $this->mutex->release();
            }

            if ($status === false && !is_dir($dir) && !str_contains((string) $errorMessage, 'File exists')) {
                throw new UnexpectedValueException(
                    sprintf(
                        'There is no existing directory at "%s" and it could not be created: ' . $errorMessage,
                        $dir
                    )
                );
            }
        }
        $this->dirCreated = true;
    }
}
