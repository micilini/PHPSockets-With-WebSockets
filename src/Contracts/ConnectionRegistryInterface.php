<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Contracts;

use Micilini\PhpSockets\Connection\Connection;

interface ConnectionRegistryInterface
{
    public function add(Connection $connection): void;

    public function remove(string $connectionId): void;

    public function get(string $connectionId): ?Connection;

    public function findBySocketId(int $socketId): ?Connection;

    public function count(): int;

    /**
     * @return list<Connection>
     */
    public function all(): array;
}
