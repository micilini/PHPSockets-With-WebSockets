<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Integration\Chat;

use Micilini\PhpSockets\Chat\ChatServer;
use Micilini\PhpSockets\Chat\Room;
use Micilini\PhpSockets\Chat\RoomManager;
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

final class PrivateGroupMessagingTest extends TestCase
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

    public function testPrivateGroupMessageIsDeliveredOnlyToMembers(): void
    {
        $server = ChatServer::create(ServerConfig::new(), ChatConfig::new());
        [$williamConnection, $williamSocket] = $this->authenticatedConnection($server, 'conn_william', 'William');
        [$anaConnection, $anaSocket] = $this->authenticatedConnection($server, 'conn_ana', 'Ana');
        [$brunoConnection, $brunoSocket] = $this->authenticatedConnection($server, 'conn_bruno', 'Bruno');
        [$carlaConnection, $carlaSocket] = $this->authenticatedConnection($server, 'conn_carla', 'Carla');

        $this->drainAvailableEnvelopes($williamSocket);
        $this->drainAvailableEnvelopes($anaSocket);
        $this->drainAvailableEnvelopes($brunoSocket);
        $this->drainAvailableEnvelopes($carlaSocket);

        $this->dispatchClientMessage($server, $williamConnection, [
            'type' => 'room.create',
            'payload' => [
                'type' => Room::TYPE_PRIVATE_GROUP,
                'name' => 'Project secret',
                'participantUserIds' => [$anaConnection->userId(), $brunoConnection->userId()],
            ],
        ]);

        $williamRoom = $this->receiveServerEnvelope($williamSocket, 'room.created');
        $anaRoom = $this->receiveServerEnvelope($anaSocket, 'room.created');
        $brunoRoom = $this->receiveServerEnvelope($brunoSocket, 'room.created');
        $carlaEnvelopes = $this->drainAvailableEnvelopes($carlaSocket);
        $roomPayload = $williamRoom['payload']['room'] ?? null;

        self::assertIsArray($roomPayload);
        self::assertSame($roomPayload, $anaRoom['payload']['room'] ?? null);
        self::assertSame($roomPayload, $brunoRoom['payload']['room'] ?? null);
        self::assertFalse($this->hasEnvelopeType($carlaEnvelopes, 'room.created'));

        $roomId = (string) ($roomPayload['id'] ?? '');
        $room = $server->kernel()->roomStore()->find($roomId);

        self::assertInstanceOf(Room::class, $room);
        self::assertSame(Room::TYPE_PRIVATE_GROUP, $room->type);
        self::assertTrue($room->hasMember((string) $williamConnection->userId()));
        self::assertTrue($room->hasMember((string) $anaConnection->userId()));
        self::assertTrue($room->hasMember((string) $brunoConnection->userId()));
        self::assertFalse($room->hasMember((string) $carlaConnection->userId()));

        $this->dispatchClientMessage($server, $williamConnection, [
            'type' => 'room.message',
            'payload' => [
                'roomId' => $roomId,
                'text' => 'Group private hello',
                'clientMessageId' => 'client_group_123',
            ],
        ]);

        $williamMessage = $this->receiveServerEnvelope($williamSocket, 'message.received');
        $anaMessage = $this->receiveServerEnvelope($anaSocket, 'message.received');
        $brunoMessage = $this->receiveServerEnvelope($brunoSocket, 'message.received');
        $carlaEnvelopes = $this->drainAvailableEnvelopes($carlaSocket);
        $message = $williamMessage['payload']['message'] ?? null;

        self::assertIsArray($message);
        self::assertSame($message, $anaMessage['payload']['message'] ?? null);
        self::assertSame($message, $brunoMessage['payload']['message'] ?? null);
        self::assertSame('client_group_123', $message['metadata']['clientMessageId'] ?? null);
        self::assertSame('Group private hello', $message['body'] ?? null);
        self::assertFalse($this->hasEnvelopeType($carlaEnvelopes, 'message.received'));

        $messages = $server->kernel()->messageStore()->messagesForRoom($roomId);

        self::assertSame('Group private hello', $messages[0]->body);
        self::assertSame('client_group_123', $messages[0]->metadata['clientMessageId'] ?? null);

        $this->expectException(RoomAccessDeniedException::class);

        (new RoomManager($server->kernel()->roomStore()))->assertMember(
            $roomId,
            (string) $carlaConnection->userId(),
        );
    }

    public function testPrivateGroupTypingIsDeliveredOnlyToRoomMembers(): void
    {
        $server = ChatServer::create(ServerConfig::new(), ChatConfig::new());
        [$williamConnection, $williamSocket] = $this->authenticatedConnection($server, 'conn_william', 'William');
        [$anaConnection, $anaSocket] = $this->authenticatedConnection($server, 'conn_ana', 'Ana');
        [$brunoConnection, $brunoSocket] = $this->authenticatedConnection($server, 'conn_bruno', 'Bruno');
        [, $carlaSocket] = $this->authenticatedConnection($server, 'conn_carla', 'Carla');

        $this->dispatchClientMessage($server, $williamConnection, [
            'type' => 'room.create',
            'payload' => [
                'type' => Room::TYPE_PRIVATE_GROUP,
                'participantUserIds' => [$anaConnection->userId(), $brunoConnection->userId()],
            ],
        ]);

        $roomEnvelope = $this->receiveServerEnvelope($williamSocket, 'room.created');
        $roomId = (string) ($roomEnvelope['payload']['room']['id'] ?? '');

        $this->drainAvailableEnvelopes($anaSocket);
        $this->drainAvailableEnvelopes($brunoSocket);
        $this->drainAvailableEnvelopes($carlaSocket);

        $this->dispatchClientMessage($server, $williamConnection, [
            'type' => 'typing.start',
            'payload' => [
                'scope' => 'room',
                'roomId' => $roomId,
            ],
        ]);

        $anaTyping = $this->receiveServerEnvelope($anaSocket, 'typing.started');
        $brunoTyping = $this->receiveServerEnvelope($brunoSocket, 'typing.started');
        $carlaEnvelopes = $this->drainAvailableEnvelopes($carlaSocket);

        self::assertSame('room', $anaTyping['payload']['scope'] ?? null);
        self::assertSame('room', $brunoTyping['payload']['scope'] ?? null);
        self::assertSame($roomId, $anaTyping['payload']['roomId'] ?? null);
        self::assertSame($roomId, $brunoTyping['payload']['roomId'] ?? null);
        self::assertFalse($this->hasEnvelopeType($carlaEnvelopes, 'typing.started'));
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
