<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Guards that the kernel still boots when PHP has no error handler registered yet.
 *
 * With coroutines on, SwooleBundle::boot() hands the pooled ErrorHandler to
 * ErrorHandler::register(). When get_error_handler() is null that method marks the handler as the
 * root one by writing its private $isRoot - and the handler is a generated proxy. ProxyManager
 * resolves the scope of a private property write from the calling frame's object, which register()
 * has none of, being static; it falls back to a scope that cannot see ErrorHandler's privates and
 * the write fatals with "Cannot access private property Symfony\Component\ErrorHandler\ErrorHandler::$isRoot".
 *
 * Every Symfony runtime installs an error handler long before the kernel boots, so nothing that goes
 * through bin/console or the server ever reaches that branch. A PHPUnit extension constructing a
 * kernel to get at the container does, and the fatal comes out of the extension bootstrap - where it
 * takes down not just the boot but every test that needed the container, with a message that says
 * nothing about coroutines.
 *
 * @see \SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\SwooleBundle::boot()
 */
final class KernelBootWithoutErrorHandlerTest extends TestCase
{
    /**
     * An environment of this test's own, so the container boot-kernel.php compiles - which carries an
     * include_once of the script itself - is never loaded by another test's process.
     */
    private const string ENV = 'coroutines_boot';

    private const string SCRIPT = __DIR__ . '/../Fixtures/Symfony/app/boot-kernel.php';

    private const int PRECONDITION_NOT_MET = 2;

    /**
     * Compiling the container from scratch is the slow part and happens on the first run of a given
     * var directory.
     */
    private const float TIMEOUT_SECONDS = 120.0;

    public function testTheKernelBootsWhenPhpHasNoErrorHandlerYet(): void
    {
        $boot = new Process(
            [PHP_BINARY, (string) realpath(self::SCRIPT)],
            null,
            ['APP_ENV' => self::ENV, 'APP_RUNTIME_MODE' => 'web=1&worker=1'],
            null,
            self::TIMEOUT_SECONDS,
        );

        $boot->run();

        self::assertNotSame(
            self::PRECONDITION_NOT_MET,
            $boot->getExitCode(),
            'something registered an error handler before the kernel booted, so the branch this test '
                . 'exists for was never taken: ' . $boot->getErrorOutput(),
        );

        self::assertSame(
            0,
            $boot->getExitCode(),
            sprintf("booting the kernel failed.\nstdout: %s\nstderr: %s", $boot->getOutput(), $boot->getErrorOutput()),
        );

        self::assertStringContainsString('BOOTED', $boot->getOutput());
    }
}
