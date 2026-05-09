<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Connection;

use Micilini\PhpSockets\Connection\Connection;
use Micilini\PhpSockets\Connection\ConnectionRegistry;
use Micilini\PhpSockets\Protocol\FrameCodec;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Socket;

final class ConnectionRegistryTest extends TestCase
{
    private ?Socket $socket = null;

    protected function tearDown(): void
    {
        if ($this->socket instanceof Socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
    }

    public function testConnectionCanBeAddedAndFound(): void
    {
        $registry = new ConnectionRegistry();
        $connection = $this->connection('conn_test');

        $registry->add($connection);

        self::assertSame(1, $registry->count());
        self::assertSame($connection, $registry->get('conn_test'));
        self::assertSame($connection, $registry->findBySocketId($connection->socketId()));
        self::assertSame([$connection], $registry->all());
    }

    public function testConnectionCanBeRemoved(): void
    {
        $registry = new ConnectionRegistry();
        $connection = $this->connection('conn_test');

        $registry->add($connection);
        $registry->remove('conn_test');

        self::assertSame(0, $registry->count());
        self::assertNull($registry->get('conn_test'));
        self::assertNull($registry->findBySocketId($connection->socketId()));
        self::assertSame([], $registry->all());
    }

    private function connection(string $id): Connection
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($socket === false) {
            throw new RuntimeException('Failed to create a test socket.');
        }

        $this->socket = $socket;

        return new Connection($id, $socket, new FrameCodec());
    }
}
