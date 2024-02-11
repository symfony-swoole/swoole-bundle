<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server;

use Exception;

final class IntMother
{
    public static function random(): int
    {
        try {
            return random_int(0, 10000);
        } catch (Exception) {
            return 0;
        }
    }

    public static function randomPositive(): int
    {
        try {
            return random_int(1, 10000);
        } catch (Exception) {
            return 0;
        }
    }
}
