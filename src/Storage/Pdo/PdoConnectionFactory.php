<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Storage\Pdo;

use PDO;

final readonly class PdoConnectionFactory
{
    /**
     * @param array<int, mixed> $options
     */
    public static function create(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        array $options = [],
    ): PDO {
        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        return new PDO($dsn, $username, $password, $options + $defaultOptions);
    }

    public static function sqlite(string $databasePath): PDO
    {
        return self::create('sqlite:' . $databasePath);
    }

    public static function sqliteMemory(): PDO
    {
        return self::create('sqlite::memory:');
    }
}
