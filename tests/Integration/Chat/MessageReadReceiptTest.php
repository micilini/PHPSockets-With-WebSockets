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

final class MessageReadReceiptTest extends TestCase
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

    public function testGlobalMessagePreservesClientMessageIdAndReadReceiptIsBroadcast(): void
    {
        $server = ChatServer::create(ServerConfig::new(), ChatConfig::new());
        [$williamConnection, $williamSocket] = $this->registeredConnection($server, 'conn_william');
        [$anaConnection] = $this->registeredConnection($server, 'conn_ana');

        $this->dispatchClientMessage($server, $williamConnection, [
            'type' => 'auth.join',
            'payload' => [
                'displayName' => 'William',
            ],
        ]);

        $this->dispatchClientMessage($server, $anaConnection, [
            'type' => 'auth.join',
            'payload' => [
                'displayName' => 'Ana',
            ],
        ]);

        $this->dispatchClientMessage($server, $williamConnection, [
            'type' => 'message.global',
            'payload' => [
                'text' => 'Receipt test',
                'clientMessageId' => 'client_test_123',
            ],
        ]);

        $messages = $server->kernel()->messageStore()->messagesForRoom('global');

        self::assertSame('client_test_123', $messages[0]->metadata['clientMessageId'] ?? null);

        $this->dispatchClientMessage($server, $anaConnection, [
            'type' => 'message.read',
            'payload' => [
                'messageId' => $messages[0]->id,
                'roomId' => 'global',
            ],
        ]);

        $broadcast = $this->receiveServerEnvelope($williamSocket, 'message.read');

        self::assertSame('message.read', $broadcast['type']);
        self::assertSame($messages[0]->id, $broadcast['payload']['messageId'] ?? null);
        self::assertSame($anaConnection->userId(), $broadcast['payload']['userId'] ?? null);
        self::assertSame('Ana', $broadcast['payload']['displayName'] ?? null);
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

    /**
     * @return array{0: Connection, 1: Socket}
     */
    private function registeredConnection(ChatServer $server, string $id): array
    {
        [$clientSocket, $peerSocket] = $this->connectedSocketPair();

        $connection = new Connection($id, $peerSocket, new FrameCodec());

        $server->webSocketServer()->connections()->add($connection);

        return [$connection, $clientSocket];
    }

    /**
     * @return array<string, mixed>
     */
    private function receiveServerEnvelope(Socket $socket, string $expectedType): array
    {
        $codec = new FrameCodec();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $data = '';
            $bytes = socket_recv($socket, $data, 8192, 0);

            if ($bytes === false || $bytes === 0) {
                continue;
            }

            foreach ($codec->decodeAll($data, fromClient: false) as $frame) {
                $envelope = json_decode($frame->payload, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($envelope) && ($envelope['type'] ?? null) === $expectedType) {
                    return $envelope;
                }
            }
        }

        throw new RuntimeException("Expected server envelope {$expectedType} was not received.");
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
