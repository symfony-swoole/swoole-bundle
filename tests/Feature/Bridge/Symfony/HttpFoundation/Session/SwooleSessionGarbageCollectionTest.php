<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature\Bridge\Symfony\HttpFoundation\Session;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SwooleBundle\SwooleBundle\Server\Session\SwooleTableStorage;

final class SwooleSessionGarbageCollectionTest extends TestCase
{
    public function testGarbageCollectRemovesExpiredSessions(): void
    {
        $storage = SwooleTableStorage::fromDefaults(10, 1024);

        $reflection = new ReflectionClass($storage);
        $table = $reflection->getProperty('sharedMemory')->getValue($storage);

        // Insert expired sessions directly into the Table to bypass TTL > 0 validation.
        // expires_at in the past means GC should delete these.
        $table->set('expired_1', ['data' => json_encode(['x' => 'stale']), 'expires_at' => time() - 10]);
        $table->set('expired_2', ['data' => json_encode(['x' => 'old']), 'expires_at' => time() - 1]);
        // Insert a valid session through the normal API
        $storage->set('valid', json_encode(['x' => 'current']), 3600);

        $this->assertSame(3, $table->count(), 'Expected 3 rows before GC');

        $storage->garbageCollect();

        $this->assertSame(1, $table->count(), 'Expected 1 row after GC (expired rows removed)');
        $this->assertNotNull($storage->get('valid'), 'Valid session must still be accessible');
    }

    public function testGarbageCollectPreservesValidSessions(): void
    {
        $storage = SwooleTableStorage::fromDefaults(10, 1024);

        $storage->set('session_a', json_encode(['user' => 1]), 3600);
        $storage->set('session_b', json_encode(['user' => 2]), 7200);

        $reflection = new ReflectionClass($storage);
        $table = $reflection->getProperty('sharedMemory')->getValue($storage);

        $storage->garbageCollect();

        $this->assertSame(2, $table->count(), 'Both valid sessions must remain after GC');
        $this->assertNotNull($storage->get('session_a'));
        $this->assertNotNull($storage->get('session_b'));
    }
}
