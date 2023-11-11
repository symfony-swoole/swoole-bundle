<?php

declare(strict_types=1);

namespace K911\Swoole\Tests\Fixtures\Symfony\CoverageBundle\Coverage;

use K911\Swoole\Common\System\System;

final class NameGenerator
{
    private static ?System $system = null;

    public static function nameForUseCase(string $useCaseName): string
    {
        return \sprintf('%s_%s', $useCaseName, self::getSystem()->extension()->toString());
    }

    public static function nameForUseCaseAndCommand(string $useCaseName, string $commandName): string
    {
        $slug = str_replace(['-', ':'], '_', $commandName);

        return \sprintf(
            '%s_%s_%s_%s',
            $useCaseName,
            $slug,
            self::getSystem()->extension()->toString(),
            gethostname(),
        );
    }

    private static function getSystem(): System
    {
        if (null === self::$system) {
            self::$system = System::create();
        }

        return self::$system;
    }
}
