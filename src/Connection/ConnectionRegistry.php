<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Connection;

use Micilini\PhpSockets\Contracts\ConnectionRegistryInterface;

final class ConnectionRegistry implements ConnectionRegistryInterface
{
    /**
     * @var array<string, Connection>
     */
    private array $connections = [];

    /**
     * @var array<int, string>
     */
    private array $connectionIdsBySocketId = [];

    public function add(Connection $connection): void
    {
        $this->connections[$connection->id()] = $connection;
        $this->connectionIdsBySocketId[$connection->socketId()] = $connection->id();
    }

    public function remove(string $connectionId): void
    {
        $connection = $this->connections[$connectionId] ?? null;

        if (!$connection instanceof Connection) {
            return;
        }

        unset($this->connections[$connectionId], $this->connectionIdsBySocketId[$connection->socketId()]);
    }

    public function get(string $connectionId): ?Connection
    {
        return $this->connections[$connectionId] ?? null;
    }

    public function findBySocketId(int $socketId): ?Connection
    {
        $connectionId = $this->connectionIdsBySocketId[$socketId] ?? null;

        if ($connectionId === null) {
            return null;
        }

        return $this->get($connectionId);
    }

    public function count(): int
    {
        return count($this->connections);
    }

    public function all(): array
    {
        return array_values($this->connections);
    }
}
