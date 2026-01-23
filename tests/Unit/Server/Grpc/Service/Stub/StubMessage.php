<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Grpc\Service\Stub;

use Google\Protobuf\Internal\Message;

class StubMessage extends Message
{
    public function __construct() {
    }
    public function serializeToString(): string { return 'payload'; }
    public function mergeFromString($data): void {}
}
