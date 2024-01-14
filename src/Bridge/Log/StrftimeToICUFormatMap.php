<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Log;

use Webmozart\Assert\Assert;

/**
 * Translate a strftime format to an ICU date/time format.
 *
 * This will translate all but %X, %x, and %c, for which there are no ICU
 * equivalents.
 *
 * @see https://www.php.net/strftime for PHP strftime format strings
 * @see https://unicode-org.github.io/icu/userguide/format_parse/datetime/ for ICU Date/Time format strings
 */
final class StrftimeToICUFormatMap
{
    public static function mapStrftimeToICU(string $format, \DateTimeInterface $requestTime): string
    {
        return \preg_replace_callback(
            '/(?P<token>%[aAbBcCdDeFgGhHIjklmMpPrRsSTuUVwWxXyYzZ])/',
            self::generateMapCallback($requestTime),
            $format
        );
    }

    /**
     * @return callable(array<array-key, string>):string
     */
    private static function generateMapCallback(\DateTimeInterface $requestTime): callable
    {
        return static function (array $matches) use ($requestTime): string {
            Assert::keyExists($matches, 'token');

            return match (true) {
                '%a' === $matches['token'] => 'eee',
                '%A' === $matches['token'] => 'eeee',
                '%b' === $matches['token'] => 'MMM',
                '%B' === $matches['token'] => 'MMMM',
                '%C' === $matches['token'] => 'yy',
                '%d' === $matches['token'] => 'dd',
                '%D' === $matches['token'] => 'MM/dd/yy',
                '%e' === $matches['token'] => ' d',
                '%F' === $matches['token'] => 'y-MM-dd',
                '%g' === $matches['token'] => 'yy',
                '%G' === $matches['token'] => 'y',
                '%h' === $matches['token'] => 'MMM',
                '%H' === $matches['token'] => 'HH',
                '%I' === $matches['token'] => 'KK',
                '%j' === $matches['token'] => 'D',
                '%k' === $matches['token'] => ' H',
                '%l' === $matches['token'] => ' h',
                '%m' === $matches['token'] => 'MM',
                '%M' === $matches['token'] => 'mm',
                '%p' === $matches['token'] => 'a',
                '%P' === $matches['token'] => 'a',
                '%r' === $matches['token'] => ' h:mm:ss a',
                '%R' === $matches['token'] => 'HH:mm',
                '%S' === $matches['token'] => 'ss',
                '%s' === $matches['token'] => (string) $requestTime->getTimestamp(),
                '%T' === $matches['token'] => 'HH:mm:ss',
                '%u' === $matches['token'] => 'e',
                '%U' === $matches['token'] => 'ww',
                '%w' === $matches['token'] => 'c',
                '%W' === $matches['token'] => 'ww',
                '%V' === $matches['token'] => 'ww',
                '%y' === $matches['token'] => 'yy',
                '%Y' === $matches['token'] => 'y',
                '%z' === $matches['token'] => 'xx',
                '%Z' === $matches['token'] => 'z',
                default => throw new \RuntimeException(\sprintf('The request time format token "%s" is unsupported; please use ICU Date/Time format codes', $matches['token'])),
            };
        };
    }
}
