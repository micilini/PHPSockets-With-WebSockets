<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Laravel\Commands;

use Illuminate\Console\Command;
use Micilini\PhpSockets\Database\MigrationRunner;
use Micilini\PhpSockets\Laravel\PhpSocketsManager;
use Throwable;

final class MigrateCommand extends Command
{
    protected $signature = 'phpsockets:migrate
        {--driver= : Storage driver: sqlite, mysql or pgsql}
        {--database= : SQLite database path override}
        {--force : Run without confirmation in production}';

    protected $description = 'Run PHPSockets database migrations.';

    public function handle(PhpSocketsManager $manager): int
    {
        $driver = $this->option('driver');
        $driver = is_string($driver) && $driver !== ''
            ? strtolower(trim($driver))
            : $manager->storageDriver();

        if ($driver === 'memory') {
            $this->components->warn('The memory storage driver does not need migrations.');

            return self::SUCCESS;
        }

        if (!in_array($driver, ['sqlite', 'mysql', 'pgsql'], true)) {
            $this->components->error("Unsupported migration driver: {$driver}");

            return self::FAILURE;
        }

        if ($this->laravel->environment('production') && !$this->option('force')) {
            if (!$this->confirm('You are running PHPSockets migrations in production. Continue?')) {
                return self::FAILURE;
            }
        }

        $database = $this->option('database');

        try {
            $pdo = $manager->pdo(
                driver: $driver,
                databaseOverride: is_string($database) && $database !== '' ? $database : null,
            );

            (new MigrationRunner($pdo))->run($driver);

            $this->components->info("PHPSockets {$driver} migrations completed.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
