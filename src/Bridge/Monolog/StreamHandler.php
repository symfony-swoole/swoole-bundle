<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Monolog;

use Composer\InstalledVersions;
use K911\Swoole\Component\Locking\Mutex;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Utils;

/*
 * This is an override of the original class from Monolog
 * It makes the error handler method public, so there are no exceptions thrown if the error handler calls it
 * from outside the class.
 * Also, there is a mutex for setting and restoring error handlers, just to be sure, that nothing breaks.
 */
if (version_compare(InstalledVersions::getVersion('monolog/monolog'), '3.0.0') >= 0) {
    class StreamHandler extends AbstractProcessingHandler
    {
        protected const MAX_CHUNK_SIZE = 2147483647;
        /** 10MB */
        protected const DEFAULT_CHUNK_SIZE = 10 * 1024 * 1024;
        protected int $streamChunkSize;
        /** @var null|resource */
        protected $stream;
        protected string|null $url = null;
        protected int|null $filePermission;
        protected bool $useLocking;
        private string|null $errorMessage = null;
        /** @var null|true */
        private bool|null $dirCreated = null;

        private Mutex $mutex;

        /**
         * @param resource|string $stream         If a missing path can't be created, an UnexpectedValueException will be thrown on first write
         * @param null|int        $filePermission Optional file permissions (default (0644) are only for owner read/write)
         * @param bool            $useLocking     Try to lock log file before doing any writes
         *
         * @throws \InvalidArgumentException If stream is not a resource or string
         */
        public function __construct($stream, int|string|Level $level = Level::Debug, bool $bubble = true, ?int $filePermission = null, bool $useLocking = false)
        {
            parent::__construct($level, $bubble);

            if (($phpMemoryLimit = Utils::expandIniShorthandBytes(ini_get('memory_limit'))) !== false) {
                if ($phpMemoryLimit > 0) {
                    // use max 10% of allowed memory for the chunk size, and at least 100KB
                    $this->streamChunkSize = min(static::MAX_CHUNK_SIZE, max((int) ($phpMemoryLimit / 10), 100 * 1024));
                } else {
                    // memory is unlimited, set to the default 10MB
                    $this->streamChunkSize = static::DEFAULT_CHUNK_SIZE;
                }
            } else {
                // no memory limit information, set to the default 10MB
                $this->streamChunkSize = static::DEFAULT_CHUNK_SIZE;
            }

            if (is_resource($stream)) {
                $this->stream = $stream;

                stream_set_chunk_size($this->stream, $this->streamChunkSize);
            } elseif (is_string($stream)) {
                $this->url = Utils::canonicalizePath($stream);
            } else {
                throw new \InvalidArgumentException('A stream must either be a resource or a string.');
            }

            $this->filePermission = $filePermission;
            $this->useLocking = $useLocking;
        }

        public function close(): void
        {
            if (null !== $this->url && is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->stream = null;
            $this->dirCreated = null;
        }

        /**
         * Return the currently active stream if it is open.
         *
         * @return null|resource
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

        public function customErrorHandler(int $code, string $msg): bool
        {
            $this->errorMessage = preg_replace('{^(fopen|mkdir)\(.*?\): }', '', $msg);

            return true;
        }

        protected function write(LogRecord $record): void
        {
            if (!is_resource($this->stream)) {
                $url = $this->url;
                if (null === $url || '' === $url) {
                    throw new \LogicException('Missing stream url, the stream can not be opened. This may be caused by a premature call to close().'.Utils::getRecordMessageForException($record));
                }
                $this->createDir($url);
                $this->errorMessage = null;

                try {
                    $this->mutex->acquire();
                    set_error_handler([$this, 'customErrorHandler']);
                    $stream = fopen($url, 'a');
                    if (null !== $this->filePermission) {
                        @chmod($url, $this->filePermission);
                    }
                    restore_error_handler();
                } finally {
                    $this->mutex->release();
                }

                if (!is_resource($stream)) {
                    $this->stream = null;

                    throw new \UnexpectedValueException(sprintf('The stream or file "%s" could not be opened in append mode: '.$this->errorMessage, $url).Utils::getRecordMessageForException($record));
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

            if ($this->useLocking) {
                flock($stream, LOCK_UN);
            }
        }

        /**
         * Write to stream.
         *
         * @param resource $stream
         */
        protected function streamWrite($stream, LogRecord $record): void
        {
            fwrite($stream, (string) $record->formatted);
        }

        private function getDirFromStream(string $stream): ?string
        {
            $pos = strpos($stream, '://');
            if (false === $pos) {
                return dirname($stream);
            }

            if ('file://' === substr($stream, 0, 7)) {
                return dirname(substr($stream, 7));
            }

            return null;
        }

        private function createDir(string $url): void
        {
            // Do not try to create dir if it has already been tried.
            if (true === $this->dirCreated) {
                return;
            }

            $dir = $this->getDirFromStream($url);
            if (null !== $dir && !is_dir($dir)) {
                $this->errorMessage = null;

                try {
                    $this->mutex->acquire();
                    set_error_handler([$this, 'customErrorHandler']);
                    $status = mkdir($dir, 0777, true);
                    restore_error_handler();
                } finally {
                    $this->mutex->release();
                }

                if (false === $status && !is_dir($dir) && false === strpos((string) $this->errorMessage, 'File exists')) {
                    throw new \UnexpectedValueException(sprintf('There is no existing directory at "%s" and it could not be created: '.$this->errorMessage, $dir));
                }
            }
            $this->dirCreated = true;
        }
    }
} elseif (version_compare(InstalledVersions::getVersion('monolog/monolog'), '2.3.3') >= 0) {
    /**
     * Stores to any stream resource.
     *
     * Can be used to store into php://stderr, remote and local files, etc.
     *
     * @author Jordi Boggiano <j.boggiano@seld.be>
     *
     * @phpstan-import-type FormattedRecord from AbstractProcessingHandler
     */
    class StreamHandler extends AbstractProcessingHandler
    {
        /** @const int */
        protected const MAX_CHUNK_SIZE = 2147483647;
        /** @const int 10MB */
        protected const DEFAULT_CHUNK_SIZE = 10 * 1024 * 1024;
        /** @var int */
        protected $streamChunkSize;
        /** @var null|resource */
        protected $stream;
        /** @var ?string */
        protected $url;
        /** @var ?int */
        protected $filePermission;
        /** @var bool */
        protected $useLocking;
        /** @var ?string */
        private $errorMessage;
        /** @var null|true */
        private $dirCreated;

        private Mutex $mutex;

        /**
         * @param resource|string $stream         If a missing path can't be created, an UnexpectedValueException will be thrown on first write
         * @param null|int        $filePermission Optional file permissions (default (0644) are only for owner read/write)
         * @param bool            $useLocking     Try to lock log file before doing any writes
         *
         * @throws \InvalidArgumentException If stream is not a resource or string
         */
        public function __construct($stream, $level = Logger::DEBUG, bool $bubble = true, ?int $filePermission = null, bool $useLocking = false)
        {
            parent::__construct($level, $bubble);

            if (($phpMemoryLimit = Utils::expandIniShorthandBytes(ini_get('memory_limit'))) !== false) {
                if ($phpMemoryLimit > 0) {
                    // use max 10% of allowed memory for the chunk size, and at least 100KB
                    $this->streamChunkSize = min(static::MAX_CHUNK_SIZE, max((int) ($phpMemoryLimit / 10), 100 * 1024));
                } else {
                    // memory is unlimited, set to the default 10MB
                    $this->streamChunkSize = static::DEFAULT_CHUNK_SIZE;
                }
            } else {
                // no memory limit information, set to the default 10MB
                $this->streamChunkSize = static::DEFAULT_CHUNK_SIZE;
            }

            if (is_resource($stream)) {
                $this->stream = $stream;

                stream_set_chunk_size($this->stream, $this->streamChunkSize);
            } elseif (is_string($stream)) {
                $this->url = Utils::canonicalizePath($stream);
            } else {
                throw new \InvalidArgumentException('A stream must either be a resource or a string.');
            }

            $this->filePermission = $filePermission;
            $this->useLocking = $useLocking;
        }

        public function close(): void
        {
            if ($this->url && is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->stream = null;
            $this->dirCreated = null;
        }

        /**
         * Return the currently active stream if it is open.
         *
         * @return null|resource
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

        public function customErrorHandler(int $code, string $msg): bool
        {
            $this->errorMessage = preg_replace('{^(fopen|mkdir)\(.*?\): }', '', $msg);

            return true;
        }

        protected function write(array $record): void
        {
            if (!is_resource($this->stream)) {
                $url = $this->url;
                if (null === $url || '' === $url) {
                    throw new \LogicException('Missing stream url, the stream can not be opened. This may be caused by a premature call to close().'.Utils::getRecordMessageForException($record));
                }
                $this->createDir($url);
                $this->errorMessage = null;

                try {
                    $this->mutex->acquire();
                    set_error_handler([$this, 'customErrorHandler']);
                    $stream = fopen($url, 'a');
                    if (null !== $this->filePermission) {
                        @chmod($url, $this->filePermission);
                    }
                    restore_error_handler();
                } finally {
                    $this->mutex->release();
                }
                if (!is_resource($stream)) {
                    $this->stream = null;

                    throw new \UnexpectedValueException(sprintf('The stream or file "%s" could not be opened in append mode: '.$this->errorMessage, $url).Utils::getRecordMessageForException($record));
                }
                stream_set_chunk_size($stream, $this->streamChunkSize);
                $this->stream = $stream;
            }

            $stream = $this->stream;
            if (!is_resource($stream)) {
                throw new \LogicException('No stream was opened yet'.Utils::getRecordMessageForException($record));
            }

            if ($this->useLocking) {
                // ignoring errors here, there's not much we can do about them
                flock($stream, LOCK_EX);
            }

            $this->streamWrite($stream, $record);

            if ($this->useLocking) {
                flock($stream, LOCK_UN);
            }
        }

        /**
         * Write to stream.
         *
         * @param resource $stream
         *
         * @phpstan-param FormattedRecord $record
         */
        protected function streamWrite($stream, array $record): void
        {
            fwrite($stream, (string) $record['formatted']);
        }

        private function getDirFromStream(string $stream): ?string
        {
            $pos = strpos($stream, '://');
            if (false === $pos) {
                return dirname($stream);
            }

            if ('file://' === substr($stream, 0, 7)) {
                return dirname(substr($stream, 7));
            }

            return null;
        }

        private function createDir(string $url): void
        {
            // Do not try to create dir if it has already been tried.
            if ($this->dirCreated) {
                return;
            }

            $dir = $this->getDirFromStream($url);
            if (null !== $dir && !is_dir($dir)) {
                $this->errorMessage = null;

                try {
                    $this->mutex->acquire();
                    set_error_handler([$this, 'customErrorHandler']);
                    $status = mkdir($dir, 0777, true);
                    restore_error_handler();
                } finally {
                    $this->mutex->release();
                }

                if (false === $status && !is_dir($dir) && false === strpos((string) $this->errorMessage, 'File exists')) {
                    throw new \UnexpectedValueException(sprintf('There is no existing directory at "%s" and it could not be created: '.$this->errorMessage, $dir));
                }
            }
            $this->dirCreated = true;
        }
    }
} else {
    /**
     * Stores to any stream resource.
     *
     * Can be used to store into php://stderr, remote and local files, etc.
     *
     * @author Jordi Boggiano <j.boggiano@seld.be>
     */
    class StreamHandler extends AbstractProcessingHandler
    {
        /** @var null|resource */
        protected $stream;
        protected $url;
        protected $filePermission;
        protected $useLocking;
        /** @var null|string */
        private $errorMessage;
        private $dirCreated;

        private Mutex $mutex;

        /**
         * @param resource|string $stream
         * @param int|string      $level          The minimum logging level at which this handler will be triggered
         * @param bool            $bubble         Whether the messages that are handled can bubble up the stack or not
         * @param null|int        $filePermission Optional file permissions (default (0644) are only for owner read/write)
         * @param bool            $useLocking     Try to lock log file before doing any writes
         *
         * @throws \Exception                If a missing directory is not buildable
         * @throws \InvalidArgumentException If stream is not a resource or string
         */
        public function __construct($stream, $level = Logger::DEBUG, bool $bubble = true, ?int $filePermission = null, bool $useLocking = false)
        {
            parent::__construct($level, $bubble);
            if (is_resource($stream)) {
                $this->stream = $stream;
            } elseif (is_string($stream)) {
                $this->url = $stream;
            } else {
                throw new \InvalidArgumentException('A stream must either be a resource or a string.');
            }

            $this->filePermission = $filePermission;
            $this->useLocking = $useLocking;
        }

        public function close(): void
        {
            if ($this->url && is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->stream = null;
            $this->dirCreated = null;
        }

        /**
         * Return the currently active stream if it is open.
         *
         * @return null|resource
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

        public function setMutex(Mutex $mutex): void
        {
            $this->mutex = $mutex;
        }

        protected function write(array $record): void
        {
            if (!is_resource($this->stream)) {
                if (null === $this->url || '' === $this->url) {
                    throw new \LogicException('Missing stream url, the stream can not be opened. This may be caused by a premature call to close().');
                }
                $this->createDir();
                $this->errorMessage = null;

                try {
                    $this->mutex->acquire();
                    set_error_handler([$this, 'customErrorHandler']);
                    $this->stream = fopen($this->url, 'a');
                    if (null !== $this->filePermission) {
                        @chmod($this->url, $this->filePermission);
                    }
                    restore_error_handler();
                } finally {
                    $this->mutex->release();
                }
                if (!is_resource($this->stream)) {
                    $this->stream = null;

                    throw new \UnexpectedValueException(sprintf('The stream or file "%s" could not be opened: '.$this->errorMessage, $this->url));
                }
            }

            if ($this->useLocking) {
                // ignoring errors here, there's not much we can do about them
                flock($this->stream, LOCK_EX);
            }

            $this->streamWrite($this->stream, $record);

            if ($this->useLocking) {
                flock($this->stream, LOCK_UN);
            }
        }

        /**
         * Write to stream.
         *
         * @param resource $stream
         */
        protected function streamWrite($stream, array $record): void
        {
            fwrite($stream, (string) $record['formatted']);
        }

        private function customErrorHandler($code, $msg): bool
        {
            $this->errorMessage = preg_replace('{^(fopen|mkdir)\(.*?\): }', '', $msg);

            return true;
        }

        private function getDirFromStream(string $stream): ?string
        {
            $pos = strpos($stream, '://');
            if (false === $pos) {
                return dirname($stream);
            }

            if ('file://' === substr($stream, 0, 7)) {
                return dirname(substr($stream, 7));
            }

            return null;
        }

        private function createDir(): void
        {
            // Do not try to create dir if it has already been tried.
            if ($this->dirCreated) {
                return;
            }

            $dir = $this->getDirFromStream($this->url);
            if (null !== $dir && !is_dir($dir)) {
                $this->errorMessage = null;

                try {
                    $this->mutex->acquire();
                    set_error_handler([$this, 'customErrorHandler']);
                    $status = mkdir($dir, 0777, true);
                    restore_error_handler();
                } finally {
                    $this->mutex->release();
                }
                if (false === $status && !is_dir($dir)) {
                    throw new \UnexpectedValueException(sprintf('There is no existing directory at "%s" and its not buildable: '.$this->errorMessage, $dir));
                }
            }
            $this->dirCreated = true;
        }
    }
}
