<?php

declare(strict_types=1);

namespace Piko\Migrate\Tests\Commands;

use Dakujem\Migrun\Direction;
use Dakujem\Migrun\DiscoversMigrations;
use Dakujem\Migrun\ExecutesMigrations;
use Dakujem\Migrun\MigrationFile;
use Dakujem\Migrun\MigrationHistoryEntry;

use Dakujem\Migrun\Orchestrator;
use Dakujem\Migrun\TracksMigrations;
use DateTimeImmutable;
use GetOpt\GetOpt;
use PHPUnit\Framework\TestCase;
use Piko\Migrate\Commands\MigrateCommand;

final class MigrateCommandTest extends TestCase
{
    public function testSuccessMsgAndErrorMsgWrapAnsiColors(): void
    {
        $command = new MigrateCommand(
            $this->createOrchestrator([], []),
            $this->createStub(GetOpt::class)
        );

        self::assertSame("\033[32mOK\033[0m", $command->successMsg('OK'));
        self::assertSame("\033[31mKO\033[0m", $command->errorMsg('KO'));
    }

    public function testRunPrintsMigratedItemsAndReturnsZero(): void
    {
        $migration = new MigrationFile('/tmp/2026-07-07-create-users.php', '2026-07-07-create-users');

        $storage = $this->createMock(TracksMigrations::class);
        $finder = $this->createMock(DiscoversMigrations::class);
        $executor = $this->createMock(ExecutesMigrations::class);

        $finder->expects(self::once())
            ->method('list')
            ->willReturn([$migration]);

        $storage->expects(self::once())
            ->method('isApplied')
            ->with('2026-07-07-create-users')
            ->willReturn(false);

        $executor->expects(self::once())
            ->method('execute')
            ->with($migration, Direction::Up);

        $storage->expects(self::once())
            ->method('markApplied')
            ->with('2026-07-07-create-users');

        $command = new MigrateCommand(
            new Orchestrator($storage, $finder, $executor),
            $this->createStub(GetOpt::class)
        );

        ob_start();
        $code = $command->run();
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Migrated: 2026-07-07-create-users', $output);
    }

    public function testRollbackUsesStepOptionAndPrintsRevertedItems(): void
    {
        $at = new DateTimeImmutable('2026-07-07 12:34:56');
        $historyEntry = new MigrationHistoryEntry('2026-07-07-create-users', $at);
        $migration = new MigrationFile('/tmp/2026-07-07-create-users.php', '2026-07-07-create-users');

        $storage = $this->createMock(TracksMigrations::class);
        $finder = $this->createMock(DiscoversMigrations::class);
        $executor = $this->createMock(ExecutesMigrations::class);
        $getopt = $this->createMock(GetOpt::class);

        $getopt->expects(self::once())
            ->method('getOption')
            ->with('s')
            ->willReturn(1);

        $storage->expects(self::once())
            ->method('getApplied')
            ->willReturn([$historyEntry]);

        $finder->expects(self::once())
            ->method('find')
            ->with([$historyEntry])
            ->willReturn([
                '2026-07-07-create-users' => $migration,
            ]);

        $executor->expects(self::once())
            ->method('execute')
            ->with($migration, Direction::Down);

        $storage->expects(self::once())
            ->method('markReverted')
            ->with('2026-07-07-create-users');

        $command = new MigrateCommand(
            new Orchestrator($storage, $finder, $executor),
            $getopt
        );

        ob_start();
        $code = $command->rollback();
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Reverted: 2026-07-07-create-users', $output);
    }

    public function testRollbackUsesRetrievedStepsValueWhenGreaterThanOne(): void
    {
        $at1 = new DateTimeImmutable('2026-07-07 12:34:56');
        $at2 = new DateTimeImmutable('2026-07-07 12:35:56');
        $entry1 = new MigrationHistoryEntry('2026-07-07-create-users', $at1);
        $entry2 = new MigrationHistoryEntry('2026-07-07-create-posts', $at2);

        $migration1 = new MigrationFile('/tmp/2026-07-07-create-users.php', '2026-07-07-create-users');
        $migration2 = new MigrationFile('/tmp/2026-07-07-create-posts.php', '2026-07-07-create-posts');

        $storage = $this->createMock(TracksMigrations::class);
        $finder = $this->createMock(DiscoversMigrations::class);
        $executor = $this->createMock(ExecutesMigrations::class);
        $getopt = $this->createMock(GetOpt::class);

        $getopt->expects(self::once())
            ->method('getOption')
            ->with('s')
            ->willReturn(2);

        $storage->expects(self::once())
            ->method('getApplied')
            ->willReturn([$entry1, $entry2]);

        $finder->expects(self::once())
            ->method('find')
            ->with(self::callback(static function (array $entries): bool {
                if (2 !== count($entries)) {
                    return false;
                }

                $ids = array_map(static fn(MigrationHistoryEntry $entry): string => $entry->id(), $entries);
                sort($ids);

                return [
                    '2026-07-07-create-posts',
                    '2026-07-07-create-users',
                ] === $ids;
            }))
            ->willReturn([
                '2026-07-07-create-posts' => $migration2,
                '2026-07-07-create-users' => $migration1,
            ]);

        $executor->expects(self::exactly(2))
            ->method('execute');

        $storage->expects(self::exactly(2))
            ->method('markReverted');

        $command = new MigrateCommand(
            new Orchestrator($storage, $finder, $executor),
            $getopt
        );

        ob_start();
        $code = $command->rollback();
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Reverted: 2026-07-07-create-posts', $output);
        self::assertStringContainsString('Reverted: 2026-07-07-create-users', $output);
    }

    public function testStatusPrintsNoMigrationsWhenEmpty(): void
    {
        $command = new MigrateCommand(
            $this->createOrchestrator([], []),
            $this->createStub(GetOpt::class)
        );

        ob_start();
        $code = $command->status();
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('No migrations found.', $output);
    }

    public function testStatusPrintsTableTotalsAndMissingWarning(): void
    {
        $appliedAt = new DateTimeImmutable('2026-07-07 10:00:00');
        $missingAt = new DateTimeImmutable('2026-07-07 11:00:00');

        $applied = new MigrationHistoryEntry('2026-07-07-a-applied', $appliedAt);
        $missing = new MigrationHistoryEntry('2026-07-07-c-missing', $missingAt);

        $files = [
            new MigrationFile('/tmp/2026-07-07-a-applied.php', '2026-07-07-a-applied'),
            new MigrationFile('/tmp/2026-07-07-b-pending.php', '2026-07-07-b-pending'),
        ];

        $command = new MigrateCommand(
            $this->createOrchestrator([$applied, $missing], $files),
            $this->createStub(GetOpt::class)
        );

        ob_start();
        $code = $command->status();
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Migration ID', $output);
        self::assertStringContainsString('2026-07-07-a-applied', $output);
        self::assertStringContainsString('up', $output);
        self::assertStringContainsString('2026-07-07-b-pending', $output);
        self::assertStringContainsString('down', $output);
        self::assertStringContainsString('2026-07-07-c-missing', $output);
        self::assertStringContainsString('MISSING', $output);
        self::assertStringContainsString('WARNING! Some migration files missing since 2026-07-07 11:00:00', $output);
        self::assertStringContainsString('Total: 1 up, 1 down', $output);
    }

    /**
     * @param array<int, MigrationHistoryEntry> $applied
     * @param array<int, MigrationFile> $files
     */
    private function createOrchestrator(array $applied, array $files): Orchestrator
    {
        $storage = $this->createStub(TracksMigrations::class);
        $finder = $this->createStub(DiscoversMigrations::class);
        $executor = $this->createStub(ExecutesMigrations::class);

        $storage->method('getApplied')->willReturn($applied);
        $storage->method('isApplied')->willReturnCallback(
            static fn (string $id): bool => in_array($id, array_map(static fn (MigrationHistoryEntry $entry): string => $entry->id(), $applied), true)
        );

        $finder->method('list')->willReturn($files);
        $finder->method('find')->willReturnCallback(
            static function (array $migrations) use ($files): array {
                $byId = [];
                foreach ($files as $file) {
                    $byId[$file->id()] = $file;
                }

                $result = [];
                foreach ($migrations as $migration) {
                    $id = $migration instanceof MigrationHistoryEntry ? $migration->id() : (string) $migration;
                    if (isset($byId[$id])) {
                        $result[$id] = $byId[$id];
                    }
                }

                return $result;
            }
        );

        return new Orchestrator($storage, $finder, $executor);
    }
}
