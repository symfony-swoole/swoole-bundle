<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Unit\Server\Runtime\Watch;

use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Runtime\Watch\FileWatcher;

final class FileWatcherTest extends TestCase
{
    private FileWatcher $watcher;

    protected function setUp(): void
    {
        $this->watcher = new FileWatcher([]);
    }

    public function testNoChanges(): void
    {
        $change = $this->watcher->classify(['/app/src/A.php' => 1], ['/app/src/A.php' => 1]);
        self::assertFalse($change->hasChanges);
        self::assertSame([], $change->lintTargets);
    }

    public function testPlainPhpEditIsAChange(): void
    {
        $change = $this->watcher->classify(['/app/src/A.php' => 1], ['/app/src/A.php' => 2]);
        self::assertTrue($change->hasChanges);
        self::assertSame(['/app/src/A.php'], $change->lintTargets);
        self::assertStringContainsString('modified /app/src/A.php', $change->reason);
    }

    public function testNewPhpFileIsAChange(): void
    {
        $change = $this->watcher->classify([], ['/app/src/New.php' => 1]);
        self::assertTrue($change->hasChanges);
        self::assertSame(['/app/src/New.php'], $change->lintTargets);
        self::assertStringContainsString('added /app/src/New.php', $change->reason);
    }

    public function testDeletedPhpFileIsAChangeButNotALintTarget(): void
    {
        $change = $this->watcher->classify(['/app/src/Old.php' => 1], []);
        self::assertTrue($change->hasChanges);
        self::assertSame([], $change->lintTargets);
        self::assertStringContainsString('removed /app/src/Old.php', $change->reason);
    }

    public function testConfigYamlEditIsAChange(): void
    {
        $change = $this->watcher->classify(
            ['/app/config/services.yaml' => 1],
            ['/app/config/services.yaml' => 2],
        );
        self::assertTrue($change->hasChanges);
        self::assertSame([], $change->lintTargets);
    }

    public function testConfigPhpEditIsAChangeAndALintTarget(): void
    {
        $change = $this->watcher->classify(
            ['/app/config/services.php' => 1],
            ['/app/config/services.php' => 2],
        );
        self::assertTrue($change->hasChanges);
        self::assertSame(['/app/config/services.php'], $change->lintTargets);
    }

    public function testReasonListsEveryKindOfChange(): void
    {
        $change = $this->watcher->classify(
            ['/app/src/Old.php' => 1, '/app/src/Edited.php' => 1],
            ['/app/src/Edited.php' => 2, '/app/src/New.php' => 1],
        );

        self::assertTrue($change->hasChanges);
        self::assertStringContainsString('added /app/src/New.php', $change->reason);
        self::assertStringContainsString('removed /app/src/Old.php', $change->reason);
        self::assertStringContainsString('modified /app/src/Edited.php', $change->reason);
    }

    public function testSnapshotPicksUpWatchedFilesOnly(): void
    {
        $dir = sys_get_temp_dir() . '/fw_' . uniqid('', true);
        mkdir($dir . '/src', 0o777, true);
        mkdir($dir . '/config', 0o777, true);
        file_put_contents($dir . '/src/Controller.php', "<?php\n");
        file_put_contents($dir . '/config/services.yaml', "services:\n");
        file_put_contents($dir . '/src/notes.txt', "ignore me\n");

        $watcher = new FileWatcher([$dir . '/src', $dir . '/config']);
        $snap = $watcher->snapshot();

        self::assertArrayHasKey($dir . '/src/Controller.php', $snap);
        self::assertArrayHasKey($dir . '/config/services.yaml', $snap);
        self::assertArrayNotHasKey($dir . '/src/notes.txt', $snap);

        @unlink($dir . '/src/Controller.php');
        @unlink($dir . '/config/services.yaml');
        @unlink($dir . '/src/notes.txt');
        @rmdir($dir . '/src');
        @rmdir($dir . '/config');
        @rmdir($dir);
    }
}
