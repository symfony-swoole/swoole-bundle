<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Functions;

use OutOfRangeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function SwooleBundle\SwooleBundle\format_bytes;

final class FormatBytesTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function bytesFormattedProvider(): array
    {
        return [
            '0 bytes' => [
                0,
                '0 B',
            ],
            '100 bytes' => [
                100,
                '100 B',
            ],
            '1024 bytes' => [
                1024,
                '1 KiB',
            ],
            '2024 bytes' => [
                2024,
                '1.98 KiB',
            ],
            '20240 bytes' => [
                20240,
                '19.77 KiB',
            ],
            '2*2^30 bytes' => [
                2 * 2 ** 30,
                '2 GiB',
            ],
            'PHP_INT_MAX bytes' => [
                PHP_INT_MAX,
                '8192 PiB',
            ],
        ];
    }

    #[DataProvider('bytesFormattedProvider')]
    public function testFormatBytes(int $bytes, string $formatted): void
    {
        self::assertSame($formatted, format_bytes($bytes));
    }

    public function testNegativeBytes(): void
    {
        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Bytes number cannot be negative');
        format_bytes(-1);
    }
}
