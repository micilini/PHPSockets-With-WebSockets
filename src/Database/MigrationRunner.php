<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Database;

use Micilini\PhpSockets\Exceptions\StorageException;
use PDO;

final readonly class MigrationRunner
{
    public function __construct(
        private PDO $pdo,
        private ?string $schemaPath = null,
    ) {
    }

    public function run(string $driver): void
    {
        $this->pdo->exec($this->schemaSql($driver));
    }

    private function schemaSql(string $driver): string
    {
        $driver = strtolower(trim($driver));

        if (!in_array($driver, ['sqlite', 'mysql', 'pgsql'], true)) {
            throw new StorageException("Unsupported migration driver: {$driver}");
        }

        $path = $this->schemaPath ?? dirname(__DIR__) . '/Database/Schema/' . $driver . '.sql';

        if (!is_file($path)) {
            throw new StorageException("Migration schema file not found: {$path}");
        }

        $sql = file_get_contents($path);

        if (!is_string($sql) || trim($sql) === '') {
            throw new StorageException("Migration schema file is empty: {$path}");
        }

        return $sql;
    }
}
