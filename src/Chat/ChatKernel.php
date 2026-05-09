<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Connection\Connection;
use Micilini\PhpSockets\Contracts\ConnectionRegistryInterface;
use Micilini\PhpSockets\Contracts\MessageStoreInterface;
use Micilini\PhpSockets\Contracts\RoomStoreInterface;
use Micilini\PhpSockets\Contracts\SessionStoreInterface;
use Micilini\PhpSockets\Exceptions\InvalidPayloadException;
use Micilini\PhpSockets\Protocol\Frame;
use Micilini\PhpSockets\Protocol\Opcode;
use Micilini\PhpSockets\Server\WebSocketServer;
use Micilini\PhpSockets\Storage\InMemory\InMemoryMessageStore;
use Micilini\PhpSockets\Storage\InMemory\InMemoryRoomStore;
use Micilini\PhpSockets\Storage\InMemory\InMemorySessionStore;
use Throwable;

final class ChatKernel
{
    private readonly SessionStoreInterface $sessions;
    private readonly MessageStoreInterface $messages;
    private readonly RoomStoreInterface $rooms;
    private readonly PayloadValidator $validator;
    private readonly PresenceManager $presence;
    private readonly RoomManager $roomManager;
    private readonly DirectMessageRouter $directMessages;
    private readonly PrivateGroupRouter $privateGroups;

    public function __construct(
        private readonly ChatConfig $config,
        ?SessionStoreInterface $sessionStore = null,
        ?MessageStoreInterface $messageStore = null,
        ?RoomStoreInterface $roomStore = null,
    ) {
        $this->sessions = $sessionStore ?? new InMemorySessionStore();
        $this->messages = $messageStore ?? new InMemoryMessageStore();
        $this->rooms = $roomStore ?? new InMemoryRoomStore();
        $this->validator = new PayloadValidator();
        $this->presence = new PresenceManager(
            new UsernameNormalizer($this->config->maxDisplayNameLength),
            $this->sessions,
        );
        $this->roomManager = new RoomManager($this->rooms);
        $this->directMessages = new DirectMessageRouter($this->roomManager, $this->messages);
        $this->privateGroups = new PrivateGroupRouter($this->roomManager, $this->messages);
    }

    public function attach(WebSocketServer $server): void
    {
        $server->on('message', function (Connection $connection, Frame $frame) use ($server): void {
            $this->handleMessage($server->connections(), $connection, $frame);
        });

        $server->on('close', function (Connection $connection) use ($server): void {
            $this->handleClose($server->connections(), $connection);
        });
    }

    public function presence(): PresenceManager
    {
        return $this->presence;
    }

    public function messageStore(): MessageStoreInterface
    {
        return $this->messages;
    }

    public function roomStore(): RoomStoreInterface
    {
        return $this->rooms;
    }

    public function handleMessage(
        ConnectionRegistryInterface $connections,
        Connection $connection,
        Frame $frame,
    ): void {
        if ($frame->opcode !== Opcode::TEXT) {
            $this->sendError($connection, 'Only text frames are supported by the chat core.');
            return;
        }

        try {
            $envelope = MessageEnvelope::fromJson($frame->payload);
            $this->validator->assertEnvelope($envelope);

            match ($envelope->type) {
                'auth.join' => $this->handleJoin($connections, $connection, $envelope),
                'message.global' => $this->handleGlobalMessage($connections, $connection, $envelope),
                'message.direct' => $this->handleDirectMessage($connections, $connection, $envelope),
                'room.create' => $this->handleRoomCreate($connections, $connection, $envelope),
                'room.message' => $this->handleRoomMessage($connections, $connection, $envelope),
                default => throw new InvalidPayloadException('Unsupported message type.'),
            };
        } catch (Throwable $exception) {
            $this->sendError($connection, $exception->getMessage());
        }
    }

    private function handleJoin(
        ConnectionRegistryInterface $connections,
        Connection $connection,
        MessageEnvelope $envelope,
    ): void {
        $session = $this->presence->join($this->validator->displayName($envelope));

        $connection->setUserId($session->userId);
        $this->roomManager->joinGlobalRoom($session->userId);

        $this->sendEnvelope($connection, MessageEnvelope::server('session.accepted', [
            'session' => $session->toArray(),
        ]));

        $this->sendEnvelope($connection, MessageEnvelope::server('presence.snapshot', [
            'users' => $this->presence->snapshot(),
        ]));

        $this->broadcastAuthenticated($connections, MessageEnvelope::server('presence.user_joined', [
            'user' => $session->toArray(),
        ]));
    }

    private function handleGlobalMessage(
        ConnectionRegistryInterface $connections,
        Connection $connection,
        MessageEnvelope $envelope,
    ): void {
        $fromUserId = $this->requireAuthenticated($connection);
        $room = $this->roomManager->ensureGlobalRoom();
        $message = ChatMessage::text($room->id, $fromUserId, $this->validator->text($envelope));

        $this->messages->save($message);

        $this->broadcastAuthenticated($connections, MessageEnvelope::server('message.received', [
            'roomId' => $room->id,
            'message' => $message->toArray(),
        ]));
    }

    private function handleDirectMessage(
        ConnectionRegistryInterface $connections,
        Connection $connection,
        MessageEnvelope $envelope,
    ): void {
        $fromUserId = $this->requireAuthenticated($connection);
        $toUserId = $this->validator->targetUserId($envelope);

        $this->assertOnlineUser($toUserId);

        $message = $this->directMessages->send(
            fromUserId: $fromUserId,
            toUserId: $toUserId,
            text: $this->validator->text($envelope),
        );

        $this->deliverToUsers($connections, [$fromUserId, $toUserId], MessageEnvelope::server('message.received', [
            'roomId' => $message->roomId,
            'message' => $message->toArray(),
        ]));
    }

    private function handleRoomCreate(
        ConnectionRegistryInterface $connections,
        Connection $connection,
        MessageEnvelope $envelope,
    ): void {
        $createdByUserId = $this->requireAuthenticated($connection);
        $type = $envelope->payload['type'] ?? null;

        if ($type !== Room::TYPE_PRIVATE_GROUP) {
            throw new InvalidPayloadException('Only private group rooms can be created in this phase.');
        }

        $participantUserIds = $this->validator->participantUserIds($envelope);

        foreach ($participantUserIds as $participantUserId) {
            $this->assertOnlineUser($participantUserId);
        }

        $room = $this->privateGroups->createRoom(
            createdByUserId: $createdByUserId,
            name: $this->validator->roomName($envelope),
            participantUserIds: $participantUserIds,
            maxMembers: $this->config->maxPrivateGroupMembers,
        );

        $this->deliverToUsers($connections, $room->memberUserIds, MessageEnvelope::server('room.created', [
            'room' => $room->toArray(),
        ]));
    }

    private function handleRoomMessage(
        ConnectionRegistryInterface $connections,
        Connection $connection,
        MessageEnvelope $envelope,
    ): void {
        $fromUserId = $this->requireAuthenticated($connection);
        $roomId = $this->validator->roomId($envelope);
        $room = $this->roomManager->assertMember($roomId, $fromUserId);
        $message = $this->privateGroups->send($roomId, $fromUserId, $this->validator->text($envelope));

        $this->deliverToUsers($connections, $room->memberUserIds, MessageEnvelope::server('message.received', [
            'roomId' => $room->id,
            'message' => $message->toArray(),
        ]));
    }

    private function handleClose(ConnectionRegistryInterface $connections, Connection $connection): void
    {
        $userId = $connection->userId();

        if ($userId === null) {
            return;
        }

        $this->presence->leave($userId);

        $this->broadcastAuthenticated($connections, MessageEnvelope::server('presence.user_left', [
            'userId' => $userId,
        ]));
    }

    private function requireAuthenticated(Connection $connection): string
    {
        $userId = $connection->userId();

        if ($userId === null) {
            throw new InvalidPayloadException('Connection is not authenticated.');
        }

        return $userId;
    }

    private function assertOnlineUser(string $userId): void
    {
        $session = $this->sessions->findByUserId($userId);

        if (!$session instanceof UserSession || !$session->connected) {
            throw new InvalidPayloadException('Target user is not online.');
        }
    }

    private function sendError(Connection $connection, string $message): void
    {
        $this->sendEnvelope($connection, MessageEnvelope::server('error', [
            'message' => $message,
        ]));
    }

    private function sendEnvelope(Connection $connection, MessageEnvelope $envelope): void
    {
        $connection->send($envelope->toJson());
    }

    private function broadcastAuthenticated(ConnectionRegistryInterface $connections, MessageEnvelope $envelope): void
    {
        foreach ($connections->all() as $connection) {
            if ($connection->userId() !== null) {
                $this->sendEnvelope($connection, $envelope);
            }
        }
    }

    /**
     * @param list<string> $userIds
     */
    private function deliverToUsers(
        ConnectionRegistryInterface $connections,
        array $userIds,
        MessageEnvelope $envelope,
    ): void {
        foreach ($connections->all() as $connection) {
            $connectionUserId = $connection->userId();

            if ($connectionUserId !== null && in_array($connectionUserId, $userIds, true)) {
                $this->sendEnvelope($connection, $envelope);
            }
        }
    }
}
