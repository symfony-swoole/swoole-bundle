<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Runtime\Watch;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Runtime\Watch\ProcessTree;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Reads a process tree the way the supervisor needs it before it kills anything.
 *
 * A fake /proc rather than the real one: the test needs a tree it chose, and the shape that matters -
 * a swoole master with a manager under it and workers under that - cannot be arranged from a test
 * process on the real system.
 *
 * @see ProcessTree
 */
#[CoversClass(ProcessTree::class)]
final class ProcessTreeTest extends TestCase
{
    private string $procDirectory;

    #[Override]
    protected function setUp(): void
    {
        $this->procDirectory = sys_get_temp_dir() . '/swoole-bundle-proc-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->procDirectory);
    }

    #[Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->procDirectory);
    }

    /**
     * The shape a swoole server actually has, and the reason this class exists: signalling the master
     * alone leaves the manager and its workers behind, holding the listen socket.
     */
    public function testThatItFindsTheWholeServerBelowTheMaster(): void
    {
        $this->fakeProcess(100, 1); // the supervisor
        $this->fakeProcess(101, 100); // master
        $this->fakeProcess(102, 101); // manager
        $this->fakeProcess(103, 102); // worker
        $this->fakeProcess(104, 102); // worker
        $this->fakeProcess(200, 1); // something else entirely

        $tree = $this->tree()->of(101);

        self::assertSame([101, 102, 103, 104], $tree);
        self::assertNotContains(200, $tree);
        self::assertNotContains(100, $tree, 'The supervisor is above the master, not below it.');
    }

    public function testThatALeafIsItsOwnWholeTree(): void
    {
        $this->fakeProcess(101, 1);

        self::assertSame([101], $this->tree()->of(101));
    }

    public function testThatAProcessThatIsNotThereHasNoTree(): void
    {
        self::assertSame([999], $this->tree()->of(999));
        self::assertFalse($this->tree()->isRunning(999));
    }

    /**
     * A process name can contain spaces and brackets, so the parent pid cannot be found by counting
     * fields from the start of the line.
     */
    public function testThatAnAwkwardProcessNameDoesNotConfuseIt(): void
    {
        $this->fakeProcess(101, 1);
        $this->fakeProcess(102, 101, 'php (worker) 1');

        self::assertSame([101, 102], $this->tree()->of(101));
    }

    /**
     * /proc is read from a live system, and a table that says a process is its own ancestor must not
     * turn into a walk that never ends.
     */
    public function testThatALoopInTheTableTerminates(): void
    {
        $this->fakeProcess(101, 102);
        $this->fakeProcess(102, 101);

        self::assertSame([101, 102], $this->tree()->of(101));
    }

    public function testThatItReportsWhatItSignalled(): void
    {
        $this->fakeProcess(101, 1);

        // Signal 0 checks that a process can be signalled without sending anything to it, so the test
        // never has to point a real signal at a pid it does not own.
        self::assertSame([], $this->tree()->kill([999], 0), 'Nothing that is gone can be signalled.');
        self::assertSame([101], $this->tree()->kill([101], 0));
    }

    private function tree(): ProcessTree
    {
        return new ProcessTree($this->procDirectory);
    }

    private function fakeProcess(int $pid, int $parentPid, string $name = 'php'): void
    {
        $directory = sprintf('%s/%d', $this->procDirectory, $pid);
        (new Filesystem())->mkdir($directory);
        file_put_contents(
            $directory . '/stat',
            sprintf('%d (%s) S %d 0 0 0 -1 0 0 0', $pid, $name, $parentPid),
        );
    }
}
