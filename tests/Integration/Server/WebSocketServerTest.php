<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Integration\Server;

use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\Connection\Connection;
use Micilini\PhpSockets\Events\ConnectionOpened;
use Micilini\PhpSockets\Protocol\FrameCodec;
use Micilini\PhpSockets\Protocol\Opcode;
use Micilini\PhpSockets\Server\WebSocketServer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Socket;

final class WebSocketServerTest extends TestCase
{
    /**
     * @var list<Socket>
     */
    private array $sockets = [];

    protected function tearDown(): void
    {
        foreach ($this->sockets as $socket) {
            socket_close($socket);
        }

        $this->sockets = [];
    }

    public function testServerStartsWithEmptyConnectionRegistry(): void
    {
        $server = new WebSocketServer(ServerConfig::new());

        self::assertSame(0, $server->connectionCount());
        self::assertSame([], $server->connections()->all());
    }

    public function testServerBroadcastsTextFramesToRegisteredConnections(): void
    {
        [$client, $peer] = $this->connectedSocketPair();
        $codec = new FrameCodec();
        $server = new WebSocketServer(ServerConfig::new());
        $connection = new Connection('conn_broadcast', $peer, $codec);

        $server->connections()->add($connection);

        self::assertSame(1, $server->broadcast('Hello runtime'));

        $received = '';
        $bytes = socket_recv($client, $received, 1024, 0);

        self::assertIsInt($bytes);
        self::assertGreaterThan(0, $bytes);

        $frame = $codec->decode($received, fromClient: false);

        self::assertSame(Opcode::TEXT, $frame->opcode);
        self::assertSame('Hello runtime', $frame->payload);
    }

    public function testServerOnMethodReceivesConnectionOpenedCallbackArguments(): void
    {
        $server = new WebSocketServer(ServerConfig::new());
        $connection = new Connection('conn_event', $this->plainSocket(), new FrameCodec());
        $receivedConnection = null;

        $server->on('open', function (Connection $connection) use (&$receivedConnection): void {
            $receivedConnection = $connection;
        });

        $server->dispatcher()->dispatch(new ConnectionOpened($connection));

        self::assertSame($connection, $receivedConnection);
    }

    /**
     * @return array{0: Socket, 1: Socket}
     */
    private function connectedSocketPair(): array
    {
        $serverSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $clientSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (!$serverSocket instanceof Socket || !$clientSocket instanceof Socket) {
            throw new RuntimeException('Failed to create test sockets.');
        }

        $this->sockets[] = $serverSocket;
        $this->sockets[] = $clientSocket;

        socket_set_option($serverSocket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($serverSocket, '127.0.0.1', 0);
        socket_listen($serverSocket, 1);

        $address = '';
        $port = 0;

        if (!socket_getsockname($serverSocket, $address, $port)) {
            throw new RuntimeException('Failed to read test server socket address.');
        }

        socket_connect($clientSocket, $address, $port);
        $peerSocket = socket_accept($serverSocket);

        if (!$peerSocket instanceof Socket) {
            throw new RuntimeException('Failed to accept test socket connection.');
        }

        $this->sockets[] = $peerSocket;

        socket_set_option($clientSocket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 1, 'usec' => 0]);

        return [$clientSocket, $peerSocket];
    }

    private function plainSocket(): Socket
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (!$socket instanceof Socket) {
            throw new RuntimeException('Failed to create a plain test socket.');
        }

        $this->sockets[] = $socket;

        return $socket;
    }
}
