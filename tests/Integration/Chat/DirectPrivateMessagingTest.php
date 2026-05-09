<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Integration\Chat;

use Micilini\PhpSockets\Chat\ChatServer;
use Micilini\PhpSockets\Chat\Room;
use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\Connection\Connection;
use Micilini\PhpSockets\Events\MessageReceived;
use Micilini\PhpSockets\Exceptions\RoomAccessDeniedException;
use Micilini\PhpSockets\Protocol\Frame;
use Micilini\PhpSockets\Protocol\FrameCodec;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Socket;

final class DirectPrivateMessagingTest extends TestCase
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

    public function testDirectMessageIsDeliveredOnlyToSenderAndRecipient(): void
    {
        $server = ChatServer::create(ServerConfig::new(), ChatConfig::new());
        [$williamConnection, $williamSocket] = $this->authenticatedConnection($server, 'conn_william', 'William');
        [$anaConnection, $anaSocket] = $this->authenticatedConnection($server, 'conn_ana', 'Ana');
        [$brunoConnection, $brunoSocket] = $this->authenticatedConnection($server, 'conn_bruno', 'Bruno');

        $this->drainAvailableEnvelopes($williamSocket);
        $this->drainAvailableEnvelopes($anaSocket);
        $this->drainAvailableEnvelopes($brunoSocket);

        $this->dispatchClientMessage($server, $williamConnection, [
            'type' => 'message.direct',
            'payload' => [
                'toUserId' => $anaConnection->userId(),
                'text' => 'Private hello',
                'clientMessageId' => 'client_direct_123',
            ],
        ]);

        $williamEnvelope = $this->receiveServerEnvelope($williamSocket, 'message.received');
        $anaEnvelope = $this->receiveServerEnvelope($anaSocket, 'message.received');
        $brunoEnvelopes = $this->drainAvailableEnvelopes($brunoSocket);
        $message = $williamEnvelope['payload']['message'] ?? null;

        self::assertIsArray($message);
        self::assertSame($message, $anaEnvelope['payload']['message'] ?? null);
        self::assertSame('client_direct_123', $message['metadata']['clientMessageId'] ?? null);
        self::assertSame('Private hello', $message['body'] ?? null);
        self::assertFalse($this->hasEnvelopeType($brunoEnvelopes, 'message.received'));

        $room = $server->kernel()->roomStore()->find((string) ($message['roomId'] ?? ''));

        self::assertInstanceOf(Room::class, $room);
        self::assertSame(Room::TYPE_DIRECT, $room->type);
        self::assertTrue($room->hasMember((string) $williamConnection->userId()));
        self::assertTrue($room->hasMember((string) $anaConnection->userId()));
        self::assertFalse($room->hasMember((string) $brunoConnection->userId()));

        $messages = $server->kernel()->messageStore()->messagesForRoom($room->id);

        self::assertSame('Private hello', $messages[0]->body);
        self::assertSame('client_direct_123', $messages[0]->metadata['clientMessageId'] ?? null);

        $this->expectException(RoomAccessDeniedException::class);

        $server->kernel()->roomStore()->find($room->id);
        (new \Micilini\PhpSockets\Chat\RoomManager($server->kernel()->roomStore()))->assertMember(
            $room->id,
            (string) $brunoConnection->userId(),
        );
    }

    public function testDirectTypingIsDeliveredOnlyToRecipient(): void
    {
        $server = ChatServer::create(ServerConfig::new(), ChatConfig::new());
        [$williamConnection] = $this->authenticatedConnection($server, 'conn_william', 'William');
        [$anaConnection, $anaSocket] = $this->authenticatedConnection($server, 'conn_ana', 'Ana');
        [, $brunoSocket] = $this->authenticatedConnection($server, 'conn_bruno', 'Bruno');

        $this->drainAvailableEnvelopes($anaSocket);
        $this->drainAvailableEnvelopes($brunoSocket);

        $this->dispatchClientMessage($server, $williamConnection, [
            'type' => 'typing.start',
            'payload' => [
                'scope' => 'direct',
                'toUserId' => $anaConnection->userId(),
            ],
        ]);

        $anaEnvelope = $this->receiveServerEnvelope($anaSocket, 'typing.started');
        $brunoEnvelopes = $this->drainAvailableEnvelopes($brunoSocket);

        self::assertSame('direct', $anaEnvelope['payload']['scope'] ?? null);
        self::assertSame($williamConnection->userId(), $anaEnvelope['payload']['userId'] ?? null);
        self::assertSame($anaConnection->userId(), $anaEnvelope['payload']['toUserId'] ?? null);
        self::assertFalse($this->hasEnvelopeType($brunoEnvelopes, 'typing.started'));
    }

    /**
     * @return array{0: Connection, 1: Socket}
     */
    private function authenticatedConnection(ChatServer $server, string $id, string $displayName): array
    {
        [$connection, $socket] = $this->registeredConnection($server, $id);

        $this->dispatchClientMessage($server, $connection, [
            'type' => 'auth.join',
            'payload' => [
                'displayName' => $displayName,
            ],
        ]);

        return [$connection, $socket];
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
        socket_set_nonblock($clientSocket);

        $connection = new Connection($id, $peerSocket, new FrameCodec());

        $server->webSocketServer()->connections()->add($connection);

        return [$connection, $clientSocket];
    }

    /**
     * @return array<string, mixed>
     */
    private function receiveServerEnvelope(Socket $socket, string $expectedType): array
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            foreach ($this->receiveAvailableEnvelopes($socket, 200000) as $envelope) {
                if (($envelope['type'] ?? null) === $expectedType) {
                    return $envelope;
                }
            }
        }

        throw new RuntimeException("Expected server envelope {$expectedType} was not received.");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function drainAvailableEnvelopes(Socket $socket): array
    {
        $envelopes = [];

        do {
            $batch = $this->receiveAvailableEnvelopes($socket, 0);
            $envelopes = [...$envelopes, ...$batch];
        } while ($batch !== []);

        return $envelopes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function receiveAvailableEnvelopes(Socket $socket, int $timeoutMicroseconds): array
    {
        $readSockets = [$socket];
        $writeSockets = null;
        $exceptSockets = null;
        $changed = socket_select($readSockets, $writeSockets, $exceptSockets, 0, $timeoutMicroseconds);

        if ($changed === false || $changed === 0) {
            return [];
        }

        $data = '';
        $bytes = socket_recv($socket, $data, 8192, 0);

        if ($bytes === false || $bytes === 0) {
            return [];
        }

        $codec = new FrameCodec();
        $envelopes = [];

        foreach ($codec->decodeAll($data, fromClient: false) as $frame) {
            $envelope = json_decode($frame->payload, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($envelope)) {
                $envelopes[] = $envelope;
            }
        }

        return $envelopes;
    }

    /**
     * @param list<array<string, mixed>> $envelopes
     */
    private function hasEnvelopeType(array $envelopes, string $type): bool
    {
        foreach ($envelopes as $envelope) {
            if (($envelope['type'] ?? null) === $type) {
                return true;
            }
        }

        return false;
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
