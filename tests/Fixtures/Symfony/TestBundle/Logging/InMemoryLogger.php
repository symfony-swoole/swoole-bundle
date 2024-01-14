<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Logging;

final class InMemoryLogger
{
    /**
     * @var array<string>
     */
    private static $messages = [];

    public static function logMessage(string $message): void
    {
        self::$messages[] = $message;
        print_r($message.PHP_EOL);
    }

    /**
     * @return array<string>
     */
    public static function getAndClear(): array
    {
        $messages = self::$messages;

        return $messages;
    }
}
