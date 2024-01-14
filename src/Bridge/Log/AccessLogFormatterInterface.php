<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Log;

interface AccessLogFormatterInterface
{
    public function format(AccessLogDataMap $map): string;
}
