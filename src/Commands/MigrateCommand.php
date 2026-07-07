<?php

/**
 * This file is part of the Piko user module
 *
 * @package Piko\Migrate
 * @copyright 2026 Sylvain PHILIP.
 * @license LGPL-3.0; see LICENSE.txt
 * @link https://github.com/piko-framework/user-module
 */

declare(strict_types=1);

namespace Piko\Migrate\Commands;

use GetOpt\GetOpt;
use Dakujem\Migrun\MigrationState;
use Dakujem\Migrun\Orchestrator;

/**
 * MigrateCommand Class
 *
 * The base Command class
 *
 * @author Sylvain PHILIP <contact@sphilip.com>
 */
class MigrateCommand
{
    /** @phpstan-ignore-next-line */
    public function __construct(protected Orchestrator $orchestrator, protected GetOpt $getopt)
    {
    }

    public function successMsg(string $message): string
    {
        $green = "\033[32m";
        $reset = "\033[0m";

        return $green . $message . $reset;
    }

    public function errorMsg(string $message): string
    {
        $red = "\033[31m";
        $reset = "\033[0m";

        return $red . $message . $reset;
    }

    public function run(): int
    {
        $executed = $this->orchestrator->run();

        foreach ($executed as $migration) {
            echo $this->successMsg("Migrated: {$migration->id()}") . PHP_EOL;
        }

        return 0;
    }

    public function rollback(): int
    {
        $stepsOption = $this->getopt->getOption('s');
        $steps = is_int($stepsOption) ? $stepsOption : 1;

        $reverted = $this->orchestrator->rollback($steps);

        foreach ($reverted as $migration) {
            echo $this->successMsg("Reverted: {$migration->id()}") . PHP_EOL;
        }

        return 0;
    }

    public function status(): int
    {
        $entries = $this->orchestrator->status();

        if (empty($entries)) {
            echo "No migrations found." . PHP_EOL;
            return 0;
        }

        $idWidth = max(array_map(fn($e) => strlen($e->id), $entries));
        $idWidth = max($idWidth, 2); // minimum column width

        $header = sprintf(
            "%-{$idWidth}s  %-7s  %s",
            'Migration ID',
            'Status',
            'Applied at (UTC)' . '   ',
        );

        echo $header . PHP_EOL;
        echo str_repeat('-', strlen($header)) . PHP_EOL;

        $up = $down = 0;
        $missingSince = null;
        foreach ($entries as $entry) {
            echo sprintf(
                "%-{$idWidth}s  %-7s  %s",
                $entry->id,
                match ($entry->state) {
                    MigrationState::Applied => 'up',
                    MigrationState::Pending => 'down',
                    MigrationState::Missing => 'MISSING',
                },
                $entry->appliedAt?->format('Y-m-d H:i:s') ?? '-',
            ) . PHP_EOL;
            $up += MigrationState::Applied === $entry->state ? 1 : 0;
            $down += MigrationState::Pending === $entry->state ? 1 : 0;
            if (MigrationState::Missing === $entry->state) {
                $missingSince = $entry->appliedAt;
            }
        }

        echo str_repeat('-', strlen($header)) . PHP_EOL;
        if ($missingSince) {
            echo $this->errorMsg(
                'WARNING! Some migration files missing since ' . $missingSince->format('Y-m-d H:i:s')
            ) . PHP_EOL;
        }
        echo "Total: {$up} up, {$down} down" . PHP_EOL;

        return 0;
    }
}
