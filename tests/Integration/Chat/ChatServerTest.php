<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Integration\Chat;

use Micilini\PhpSockets\Chat\ChatServer;
use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\Connection\Connection;
use Micilini\PhpSockets\Events\MessageReceived;
use Micilini\PhpSockets\Protocol\Frame;
use Micilini\PhpSockets\Protocol\FrameCodec;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Socket;

final class ChatServerTest extends TestCase
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

    public function testAuthJoinCreatesUserSession(): void
    {
        $server = ChatServer::create(ServerConfig::new(), ChatConfig::new());
        $connection = $this->registeredConnection($server, 'conn_william');

        $this->dispatchClientMessage($server, $connection, [
            'type' => 'auth.join',
            'payload' => [
                'displayName' => 'William',
            ],
        ]);

        self::assertNotNull($connection->userId());
        self::assertSame(1, count($server->kernel()->presence()->connectedSessions()));
    }

    public function testDuplicatedDisplayNameIsRejected(): void
    {
        $server = ChatServer::create(ServerConfig::new(), ChatConfig::new());
        $firstConnection = $this->registeredConnection($server, 'conn_first');
        $secondConnection = $this->registeredConnection($server, 'conn_second');

        $this->dispatchClientMessage($server, $firstConnection, [
            'type' => 'auth.join',
            'payload' => [
                'displayName' => 'William',
            ],
        ]);

        $this->dispatchClientMessage($server, $secondConnection, [
            'type' => 'auth.join',
            'payload' => [
                'displayName' => 'william',
            ],
        ]);

        self::assertNotNull($firstConnection->userId());
        self::assertNull($secondConnection->userId());
        self::assertSame(1, count($server->kernel()->presence()->connectedSessions()));
    }

    public function testAuthenticatedUserCanSendGlobalMessage(): void
    {
        $server = ChatServer::create(ServerConfig::new(), ChatConfig::new());
        $connection = $this->registeredConnection($server, 'conn_william');

        $this->dispatchClientMessage($server, $connection, [
            'type' => 'auth.join',
            'payload' => [
                'displayName' => 'William',
            ],
        ]);

        $this->dispatchClientMessage($server, $connection, [
            'type' => 'message.global',
            'payload' => [
                'text' => 'Hello world',
            ],
        ]);

        $messages = $server->kernel()->messageStore()->messagesForRoom('global');

        self::assertSame(1, count($messages));
        self::assertSame('Hello world', $messages[0]->body);
    }

    /**
     * @param array<string, mixed> $message
     */
    private function dispatchClientMessage(ChatServer $server, Connection $connection, array $message): void
    {
        $json = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $server->webSocketServer()->dispatcher()->dispatch(
            new MessageReceived($connection, Frame::text($json)),
        );
    }

    private function registeredConnection(ChatServer $server, string $id): Connection
    {
        [, $peerSocket] = $this->connectedSocketPair();

        $connection = new Connection($id, $peerSocket, new FrameCodec());

        $server->webSocketServer()->connections()->add($connection);

        return $connection;
    }

    /**
     * @return array{0: Socket, 1: Socket}
     */
    private function connectedSocketPair(): array
    {
        $serverSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $clientSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($serverSocket === false || $clientSocket === false) {
            throw new RuntimeException('Failed to create test sockets.');
        }

        $this->sockets[] = $serverSocket;
        $this->sockets[] = $clientSocket;

        socket_set_option($serverSocket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (!socket_bind($serverSocket, '127.0.0.1', 0)) {
            throw new RuntimeException('Failed to bind test server socket.');
        }

        if (!socket_listen($serverSocket, 1)) {
            throw new RuntimeException('Failed to listen on test server socket.');
        }

        $address = '';
        $port = 0;

        if (!socket_getsockname($serverSocket, $address, $port)) {
            throw new RuntimeException('Failed to read test server socket address.');
        }

        if (!socket_connect($clientSocket, $address, $port)) {
            throw new RuntimeException('Failed to connect test client socket.');
        }

        $peerSocket = socket_accept($serverSocket);

        if ($peerSocket === false) {
            throw new RuntimeException('Failed to accept test socket connection.');
        }

        $this->sockets[] = $peerSocket;

        return [$clientSocket, $peerSocket];
    }
}
