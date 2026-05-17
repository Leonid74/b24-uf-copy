<?php

declare(strict_types=1);

/**
 * Тесты хранилища состояния.
 */

namespace Leonid74\B24UfCopy\Tests;

use Leonid74\B24UfCopy\StateStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StateStorage::class)]
final class StateStorageTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/b24uf_state_' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        foreach (glob(sys_get_temp_dir() . '/b24uf_state_*') ?: [] as $f) {
            @unlink($f);
        }
    }

    public function testInitializesWithDefaultState(): void
    {
        $storage = new StateStorage($this->tmpFile);

        self::assertSame(0, $storage->getLastProcessedId());
        self::assertFalse($storage->isFinished());
        self::assertSame(0, $storage->getStats()['scanned']);
        self::assertSame(0, $storage->getStats()['updated']);
    }

    public function testSetAndGetLastProcessedId(): void
    {
        $storage = new StateStorage($this->tmpFile);
        $storage->setLastProcessedId(42);

        self::assertSame(42, $storage->getLastProcessedId());
    }

    public function testIncrementStatAccumulates(): void
    {
        $storage = new StateStorage($this->tmpFile);
        $storage->incrementStat('scanned', 10);
        $storage->incrementStat('scanned', 5);
        $storage->incrementStat('updated', 3);

        self::assertSame(15, $storage->getStats()['scanned']);
        self::assertSame(3, $storage->getStats()['updated']);
    }

    public function testIncrementStatDefaultDeltaIsOne(): void
    {
        $storage = new StateStorage($this->tmpFile);
        $storage->incrementStat('errors');
        $storage->incrementStat('errors');

        self::assertSame(2, $storage->getStats()['errors']);
    }

    public function testSaveAndReloadPreservesState(): void
    {
        $storage = new StateStorage($this->tmpFile);
        $storage->setLastProcessedId(100);
        $storage->incrementStat('scanned', 50);
        $storage->incrementStat('updated', 40);
        $storage->save();

        $reloaded = new StateStorage($this->tmpFile);

        self::assertSame(100, $reloaded->getLastProcessedId());
        self::assertSame(50, $reloaded->getStats()['scanned']);
        self::assertSame(40, $reloaded->getStats()['updated']);
    }

    public function testResetClearsAllState(): void
    {
        $storage = new StateStorage($this->tmpFile);
        $storage->setLastProcessedId(999);
        $storage->incrementStat('scanned', 100);
        $storage->reset();

        self::assertSame(0, $storage->getLastProcessedId());
        self::assertSame(0, $storage->getStats()['scanned']);
        self::assertFalse($storage->isFinished());
    }

    public function testMarkFinishedSetsFlag(): void
    {
        $storage = new StateStorage($this->tmpFile);
        self::assertFalse($storage->isFinished());

        $storage->markFinished();

        self::assertTrue($storage->isFinished());
    }

    public function testMarkFinishedPersistsOnDisk(): void
    {
        $storage = new StateStorage($this->tmpFile);
        $storage->markFinished();

        $reloaded = new StateStorage($this->tmpFile);
        self::assertTrue($reloaded->isFinished());
    }

    public function testHandlesCorruptedFileGracefully(): void
    {
        file_put_contents($this->tmpFile, 'not valid json {{{');

        $storage = new StateStorage($this->tmpFile);

        self::assertSame(0, $storage->getLastProcessedId());
        self::assertFalse($storage->isFinished());
    }

    public function testCorruptedFileIsRenamedNotDeleted(): void
    {
        file_put_contents($this->tmpFile, '{"broken":');

        new StateStorage($this->tmpFile);

        $corrupted = glob(sys_get_temp_dir() . '/b24uf_state_*.corrupted.*');
        self::assertNotEmpty($corrupted);
    }

    public function testAtomicSaveWritesValidJson(): void
    {
        $storage = new StateStorage($this->tmpFile);
        $storage->setLastProcessedId(7);
        $storage->save();

        self::assertFileExists($this->tmpFile);
        $data = json_decode((string) file_get_contents($this->tmpFile), true);
        self::assertIsArray($data);
        self::assertSame(7, $data['last_processed_id']);
    }

    public function testSnapshotReturnsFullState(): void
    {
        $storage = new StateStorage($this->tmpFile);
        $storage->setLastProcessedId(5);
        $storage->incrementStat('updated', 3);

        $snapshot = $storage->snapshot();

        self::assertArrayHasKey('last_processed_id', $snapshot);
        self::assertArrayHasKey('stats', $snapshot);
        self::assertArrayHasKey('started_at', $snapshot);
        self::assertSame(5, $snapshot['last_processed_id']);
    }

    public function testCreatesStateDirectoryAutomatically(): void
    {
        $dir     = sys_get_temp_dir() . '/b24uf_newdir_' . uniqid('', true);
        $file    = $dir . '/progress.json';
        $storage = new StateStorage($file);
        $storage->save();

        self::assertFileExists($file);

        // cleanup
        @unlink($file);
        @rmdir($dir);
    }
}
