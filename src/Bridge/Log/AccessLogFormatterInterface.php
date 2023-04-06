<?php

declare(strict_types=1);

namespace K911\Swoole\Bridge\Log;

interface AccessLogFormatterInterface
{
    public function format(AccessLogDataMap $map): string;
}
