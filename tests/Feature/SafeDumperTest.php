<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use Closure;
use Override;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Dumper\SafeDumper;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Symfony\Component\VarDumper\VarDumper;

// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable
final class SafeDumperTest extends ServerTestCase
{
    private ?string $oldFormat = null;

    private ?Closure $prevHandler = null;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteVarDirectory();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        VarDumper::setHandler($this->prevHandler);
        $_SERVER['VAR_DUMPER_FORMAT'] = $this->oldFormat;
    }

    public function testSafeDumper(): void
    {
        $this->oldFormat = $_SERVER['VAR_DUMPER_FORMAT'] ?? null;
        unset($_SERVER['VAR_DUMPER_FORMAT']);
        $dumps = [];

        // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference
        $this->prevHandler = VarDumper::setHandler(static function ($var) use (&$dumps): void {
            $dumps[] = $var;
        });

        $this->runAsCoroutineAndWait(function (): void {
            $wg = $this->getSwoole()->waitGroup();

            for ($i = 0; $i < 100; $i++) {
                go(static function () use ($i, $wg): void {
                    $wg->add();
                    SafeDumper::dump($i);
                    $wg->done();
                });
            }

            $wg->wait();
        });

        VarDumper::setHandler($this->prevHandler);
        $_SERVER['VAR_DUMPER_FORMAT'] = $this->oldFormat;

        self::assertCount(100, $dumps);
    }

    public function testSDump(): void
    {
        $this->oldFormat = $_SERVER['VAR_DUMPER_FORMAT'] ?? null;
        unset($_SERVER['VAR_DUMPER_FORMAT']);
        $dumps = [];

        // phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference
        $this->prevHandler = VarDumper::setHandler(static function ($var) use (&$dumps): void {
            $dumps[] = $var;
        });

        $this->runAsCoroutineAndWait(function (): void {
            $wg = $this->getSwoole()->waitGroup();

            for ($i = 0; $i < 100; $i++) {
                go(static function () use ($i, $wg): void {
                    $wg->add();
                    sdump($i);
                    $wg->done();
                });
            }

            $wg->wait();
        });

        VarDumper::setHandler($this->prevHandler);
        $_SERVER['VAR_DUMPER_FORMAT'] = $this->oldFormat;

        self::assertCount(100, $dumps);
    }
}
