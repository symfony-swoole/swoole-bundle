<?php

/**
 * Boots the fixture kernel the way a PHPUnit extension does: constructed and booted directly, with
 * no Debug::enable() and no Console Application ahead of it. That leaves nothing on
 * ContextualErrorHandler's global context stack when SwooleBundle::boot() runs, which is the one
 * condition under which ErrorHandler::register() tries to claim the root handler slot.
 *
 * The fixture app's ./console cannot stand in for this - it calls Debug::enable() before building
 * the kernel, like every Symfony runtime entrypoint does, so it never reaches that branch.
 *
 * Runs in the coroutines_boot environment, which exists so that this script owns its cache
 * directory outright - see that environment's swoole.php for why sharing one is not an option.
 *
 * Prints BOOTED and exits 0 on success. Exit code 2 means the precondition was not met and the test
 * proved nothing.
 *
 * @see \SwooleBundle\SwooleBundle\Tests\Feature\KernelBootWithoutErrorHandlerTest
 */

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestAppKernel;
use Symfony\Component\Runtime\SymfonyRuntime;

require __DIR__ . '/../../../../vendor/autoload.php';

// FrameworkBundle::boot() runs before SwooleBundle::boot() and, when it finds no SymfonyRuntime,
// registers a root ErrorHandler of its own - which ContextualErrorHandler then adopts, leaving its
// context stack non-empty and the branch under test unreachable. Under a runtime FrameworkBundle
// only reads get_error_handler() and registers nothing, which is the case worth covering. The
// bundle does not depend on symfony/runtime, so where it is missing a bare declaration is enough:
// FrameworkBundle asks class_exists() and nothing here ever instantiates it.
if (!class_exists(SymfonyRuntime::class)) {
    eval('namespace Symfony\Component\Runtime; class SymfonyRuntime {}');
}

// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
$_SERVER['APP_RUNTIME_MODE'] = $_ENV['APP_RUNTIME_MODE'] = 'web=1&worker=1';

$environment = $_SERVER['APP_ENV'] ?? 'coroutines_boot';
assert(is_string($environment));
// phpcs:enable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable

$existingHandler = set_error_handler(static fn(): bool => false);
restore_error_handler();

if ($existingHandler !== null) {
    fwrite(STDERR, 'PRECONDITION FAILED: an error handler was already registered before the kernel booted.');

    exit(2);
}

(new TestAppKernel($environment, true))->boot();

echo 'BOOTED';
