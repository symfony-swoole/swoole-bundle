<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Xdebug;

use Override;
use SwooleBundle\SwooleBundle\Bridge\Xdebug\XdebugClient;

/**
 * An XdebugClient that records attaches instead of making them.
 *
 * The seam the xdebug feature tests observe through. Attaching for real needs two things a test suite
 * cannot assume: the extension loaded in the container running the server, and something listening on
 * the debug port to accept the session. Neither is what those tests are about - what they check is
 * *when* the bundle decides to attach, which is entirely the handlers' logic and is worth pinning down
 * on its own.
 *
 * Reports itself available so the handlers behave as they would with the extension present, and
 * becomes attached after the first attach, exactly as a real session does - which is what makes the
 * "attaches once per worker, not once per request" behaviour observable.
 *
 * ## Why a file
 *
 * The test watches from outside, and every attach happens in a process it does not own: an http worker
 * for a request, a task worker for a task, each worker of the server for a worker start. A file under
 * the app's var directory is the one place all of them can write and the test can read. Appends are
 * single short writes opened with 'a', which is atomic enough for the sizes involved - and the tests
 * assert on how many lines and from how many pids, never on their order.
 */
final class RecordingXdebugClient implements XdebugClient
{
    /**
     * Per-process, like a real session: this object is rebuilt in every forked worker, so each one
     * starts detached and attaches once.
     */
    private bool $attached = false;

    public function __construct(
        private readonly string $recordFile,
    ) {}

    #[Override]
    public function isAvailable(): bool
    {
        return true;
    }

    #[Override]
    public function isAttached(): bool
    {
        return $this->attached;
    }

    #[Override]
    public function attach(): void
    {
        if ($this->isAttached()) {
            return;
        }

        $this->attached = true;

        file_put_contents($this->recordFile, sprintf('%d%s', getmypid(), PHP_EOL), FILE_APPEND);
    }
}
