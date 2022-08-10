<?php
/**
 * Copyright © Upscale Software. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Upscale\Swoole\Reflection\Http\Response;

class Proxy extends \Swoole\Http\Response
{
    /**
     * @var \Swoole\Http\Response
     */
    protected $subject;

    /**
     * Inject dependencies
     *
     * @param \Swoole\Http\Response $subject
     */
    public function __construct(\Swoole\Http\Response $subject)
    {
        $this->subject = $subject;
        $this->fd = $subject->fd;
    }

    /**
     * @param string $content
     * @return mixed
     */
    public function end($content = ''): bool
    {
        return $this->subject->end($content);
    }

    /**
     * @param string $content
     * @return mixed
     */
    public function write(string $data): bool
    {
        return $this->subject->write($data);
    }

    /**
     * @param string $key
     * @param string $value
     * @param bool $format
     * @return mixed
     */
    public function header(string $key, string $value, bool $format = true): bool
    {
        $result = $this->subject->header($key, $value, $format);
        $this->header = $this->subject->header;
        return $result;
    }

    /**
     * @param string $name
     * @param string|null $value
     * @param int|null $expires
     * @param string|null $path
     * @param string|null $domain
     * @param bool|null $secure
     * @param bool|null $httponly
     * @param string|null $samesite
     * @param string|null $priority
     * @return mixed
     */
    public function cookie(
        string $key,
        ?string $value = null,
        int $expire = 0,
        string $path = '',
        string $domain = '',
        bool $secure = false,
        bool $httponly = false,
        string $samesite = '',
        string $priority = ''
    ): bool {
        $result = $this->subject->cookie(...func_get_args());
        $this->cookie = $this->subject->cookie;
        return $result;
    }

    /**
     * @param string $name
     * @param string|null $value
     * @param int|null $expires
     * @param string|null $path
     * @param string|null $domain
     * @param bool|null $secure
     * @param bool|null $httponly
     * @param string|null $samesite
     * @param string|null $priority
     * @return mixed
     */
    public function rawcookie(
        string $key,
        ?string $value = null,
        int $expire = 0,
        string $path = '',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = false,
        string $samesite = '',
        string $priority = ''
    ):bool {
        $result = $this->subject->rawcookie(...func_get_args());
        $this->cookie = $this->subject->cookie;
        return $result;
    }

    /**
     * @param int $code
     * @param string|null $reason
     * @return mixed
     */
    public function status(int $statusCode, string $reason = ''): bool
    {
        return ($reason === null)
            ? $this->subject->status($statusCode)
            : $this->subject->status($statusCode, $reason);
    }

    /**
     * @param int $level
     * @return mixed
     */
    public function gzip($level = 1)
    {
        return $this->subject->gzip($level);
    }

    /**
     * @param string $filename
     * @param int $offset
     * @param int $length
     * @return mixed
     */
    public function sendfile(string $fileName, int $offset = 0, int $length = 0): bool
    {
        return $this->subject->sendfile($fileName, $offset, $length);
    }
}
