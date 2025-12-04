<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\ConcurrentTasks;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\ConcurrentTasks\Task;

final class TasksTest extends TestCase
{
    public function testCreateAndGetTasks(): void
    {
        $task1 = Task::create(fn() => 'task1 result');
        $callback1 = $task1->getCallback();
        $this->assertIsCallable($callback1);
        $this->assertSame('task1 result', $callback1());

        // ensure serialization preserves callback behavior
        $serialized = serialize($task1);
        $unserialized = unserialize($serialized);
        $this->assertInstanceOf(Task::class, $unserialized);
        $this->assertSame('task1 result', $unserialized->getCallback()());
    }
}
