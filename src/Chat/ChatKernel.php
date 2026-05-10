<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use DateTimeImmutable;
use Micilini\PhpSockets\Chat\Bot\BotContext;
use Micilini\PhpSockets\Chat\Bot\BotManager;
use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Connection\Connection;
use Micilini\PhpSockets\Contracts\AttachmentStoreInterface;
use Micilini\PhpSockets\Contracts\ConnectionRegistryInterface;
use Micilini\PhpSockets\Contracts\MessageStoreInterface;
use Micilini\PhpSockets\Contracts\RoomStoreInterface;
use Micilini\PhpSockets\Contracts\SessionStoreInterface;
use Micilini\PhpSockets\Exceptions\InvalidPayloadException;
use Micilini\PhpSockets\Exceptions\UsernameAlreadyTakenException;
use Micilini\PhpSockets\Protocol\Frame;
use Micilini\PhpSockets\Protocol\Opcode;
use Micilini\PhpSockets\Server\WebSocketServer;
use Micilini\PhpSockets\Storage\File\FileAttachmentStore;
use Micilini\PhpSockets\Storage\InMemory\InMemoryMessageStore;
use Micilini\PhpSockets\Storage\InMemory\InMemoryRoomStore;
use Micilini\PhpSockets\Storage\InMemory\InMemorySessionStore;
use Micilini\PhpSockets\Support\RuntimePath;
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
    private readonly AttachmentValidator $attachmentValidator;
    private readonly AttachmentStoreInterface $attachments;
    private readonly BotManager $bots;

    /**
     * @var array<string, list<callable(array<string, mixed>): void>>
     */
    private array $listeners = [];

    public function __construct(
        private readonly ChatConfig $config,
        ?SessionStoreInterface $sessionStore = null,
        ?MessageStoreInterface $messageStore = null,
        ?RoomStoreInterface $roomStore = null,
        ?AttachmentStoreInterface $attachmentStore = null,
        ?BotManager $botManager = null,
    ) {
        $this->sessions = $sessionStore ?? new InMemorySessionStore();
        $this->messages = $messageStore ?? new InMemoryMessageStore();
        $this->rooms = $roomStore ?? new InMemoryRoomStore();
        $this->validator = new PayloadValidator();
        $this->attachmentValidator = new AttachmentValidator($this->config);
        $this->attachments = $attachmentStore ?? new FileAttachmentStore(RuntimePath::attachmentsDirectory());
        $this->bots = $botManager ?? new BotManager();
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

    /**
     * @param callable(array<string, mixed>): void $listener
     */
    public function on(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName] ??= [];
        $this->listeners[$eventName][] = $listener;
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

    public function bots(): BotManager
    {
        return $this->bots;
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

        $messageType = null;

        try {
            $envelope = MessageEnvelope::fromJson($frame->payload);
            $messageType = $envelope->type;

            $this->validator->assertEnvelope($envelope);

            match ($envelope->type) {
                'auth.join' => $this->handleJoin($connections, $connection, $envelope),
                'attachment.prepare' => $this->handleAttachmentPrepare($connection, $envelope),
                'message.global' => $this->handleGlobalMessage($connections, $connection, $envelope),
                'message.direct' => $this->handleDirectMessage($connections, $connection, $envelope),
                'message.file' => $this->handleFileMessage($connections, $connection, $envelope),
                'message.read' => $this->handleMessageRead($connections, $connection, $envelope),
                'room.create' => $this->handleRoomCreate($connections, $connection, $envelope),
                'room.message' => $this->handleRoomMessage($connections, $connection, $envelope),
                'typing.start' => $this->handleTypingStatus($connections, $connection, $envelope, 'typing.started'),
                'typing.stop' => $this->handleTypingStatus($connections, $connection, $envelope, 'typing.stopped'),
                default => throw new InvalidPayloadException('Unsupported message type.'),
            };
        } catch (Throwable $exception) {
            if ($messageType === 'auth.join') {
                $reason = $exception instanceof UsernameAlreadyTakenException ? 'username_taken' : 'join_failed';

                $this->sendSessionRejected($connection, $reason, $exception->getMessage());

                return;
            }

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

        $this->emit('user.joined', [
            'session' => $session,
            'connection' => $connection,
            'onlineCount' => count($this->presence->connectedSessions()),
        ]);

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
        $clientMessageId = $this->validator->clientMessageId($envelope);
        $metadata = [];

        if ($clientMessageId !== null) {
            $metadata['clientMessageId'] = $clientMessageId;
        }

        $message = ChatMessage::text(
            roomId: $room->id,
            fromUserId: $fromUserId,
            text: $this->validator->text($envelope),
            metadata: $metadata,
        );

        $this->messages->save($message);

        $this->emit('message.received', [
            'message' => $message,
            'room' => $room,
            'connection' => $connection,
            'scope' => 'global',
        ]);

        $this->broadcastAuthenticatedExcept($connections, $fromUserId, MessageEnvelope::server('typing.stopped', [
            'userId' => $fromUserId,
            'roomId' => $room->id,
        ]));

        $this->broadcastAuthenticated($connections, MessageEnvelope::server('message.received', [
            'roomId' => $room->id,
            'message' => $message->toArray(),
        ]));

        $this->dispatchBotResponses(
            connections: $connections,
            sourceMessage: $message,
            room: $room,
            connection: $connection,
            scope: 'global',
            recipientUserIds: null,
        );
    }

    private function handleDirectMessage(
        ConnectionRegistryInterface $connections,
        Connection $connection,
        MessageEnvelope $envelope,
    ): void {
        $fromUserId = $this->requireAuthenticated($connection);
        $toUserId = $this->validator->targetUserId($envelope);

        $this->assertOnlineUser($toUserId);

        $clientMessageId = $this->validator->clientMessageId($envelope);
        $metadata = [];

        if ($clientMessageId !== null) {
            $metadata['clientMessageId'] = $clientMessageId;
        }

        $message = $this->directMessages->send(
            fromUserId: $fromUserId,
            toUserId: $toUserId,
            text: $this->validator->text($envelope),
            metadata: $metadata,
        );

        $this->emit('message.received', [
            'message' => $message,
            'connection' => $connection,
            'scope' => 'direct',
            'targetUserId' => $toUserId,
        ]);

        $this->deliverToUsers($connections, [$fromUserId, $toUserId], MessageEnvelope::server('message.received', [
            'roomId' => $message->roomId,
            'message' => $message->toArray(),
        ]));

        $this->dispatchBotResponses(
            connections: $connections,
            sourceMessage: $message,
            room: $this->roomManager->createDirectRoom($fromUserId, $toUserId),
            connection: $connection,
            scope: 'direct',
            recipientUserIds: [$fromUserId, $toUserId],
        );
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

        $this->emit('room.created', [
            'room' => $room,
            'connection' => $connection,
            'createdByUserId' => $createdByUserId,
        ]);

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
        $clientMessageId = $this->validator->clientMessageId($envelope);
        $metadata = [];

        if ($clientMessageId !== null) {
            $metadata['clientMessageId'] = $clientMessageId;
        }

        $message = $this->privateGroups->send(
            roomId: $roomId,
            fromUserId: $fromUserId,
            text: $this->validator->text($envelope),
            metadata: $metadata,
        );

        $this->emit('message.received', [
            'message' => $message,
            'room' => $room,
            'connection' => $connection,
            'scope' => 'room',
        ]);

        $this->deliverToUsers($connections, $room->memberUserIds, MessageEnvelope::server('message.received', [
            'roomId' => $room->id,
            'message' => $message->toArray(),
        ]));

        $this->dispatchBotResponses(
            connections: $connections,
            sourceMessage: $message,
            room: $room,
            connection: $connection,
            scope: 'room',
            recipientUserIds: $room->memberUserIds,
        );
    }

    private function handleAttachmentPrepare(Connection $connection, MessageEnvelope $envelope): void
    {
        $this->requireAuthenticated($connection);

        try {
            $fileName = $this->attachmentValidator->fileName($envelope->payload['fileName'] ?? null);
            $mimeType = $this->attachmentValidator->mimeType($envelope->payload['mimeType'] ?? null);
            $sizeBytes = $this->attachmentValidator->sizeBytes($envelope->payload['sizeBytes'] ?? null);

            $this->sendEnvelope($connection, MessageEnvelope::server('attachment.accepted', [
                'fileName' => $fileName,
                'mimeType' => $mimeType,
                'sizeBytes' => $sizeBytes,
                'maxAttachmentBytes' => $this->config->maxAttachmentBytes,
            ]));

            return;
        } catch (InvalidPayloadException $exception) {
            $this->sendEnvelope($connection, MessageEnvelope::server('attachment.rejected', [
                'message' => $exception->getMessage(),
                'maxAttachmentBytes' => $this->config->maxAttachmentBytes,
            ]));
        }
    }

    private function handleFileMessage(
        ConnectionRegistryInterface $connections,
        Connection $connection,
        MessageEnvelope $envelope,
    ): void {
        try {
            $fromUserId = $this->requireAuthenticated($connection);
            $attachmentPayload = $this->validator->attachmentPayload($envelope);
            $fileName = $this->attachmentValidator->fileName($attachmentPayload['fileName'] ?? null);
            $mimeType = $this->attachmentValidator->mimeType($attachmentPayload['mimeType'] ?? null);
            $sizeBytes = $this->attachmentValidator->sizeBytes($attachmentPayload['sizeBytes'] ?? null);
            $content = $this->attachmentValidator->decodedContent($attachmentPayload['contentBase64'] ?? null, $sizeBytes);
            $caption = $this->validator->optionalText($envelope, 'caption');
            $target = $this->resolveFileMessageTarget($fromUserId, $envelope);
            $clientMessageId = $this->validator->clientMessageId($envelope);
            $metadata = [];

            if ($clientMessageId !== null) {
                $metadata['clientMessageId'] = $clientMessageId;
            }
        } catch (InvalidPayloadException $exception) {
            $this->sendEnvelope($connection, MessageEnvelope::server('attachment.rejected', [
                'message' => $exception->getMessage(),
                'maxAttachmentBytes' => $this->config->maxAttachmentBytes,
            ]));

            return;
        }

        $draftMessage = ChatMessage::file(
            roomId: $target['room']->id,
            fromUserId: $fromUserId,
            body: [],
            metadata: $metadata,
        );
        $attachment = $this->storeAttachment(
            messageId: $draftMessage->id,
            fileName: $fileName,
            mimeType: $mimeType,
            content: $content,
        );
        $message = new ChatMessage(
            id: $draftMessage->id,
            roomId: $draftMessage->roomId,
            fromUserId: $draftMessage->fromUserId,
            kind: $draftMessage->kind,
            body: $this->fileMessageBody($attachment, $content, $caption),
            createdAt: $draftMessage->createdAt,
            metadata: $draftMessage->metadata,
        );

        $this->messages->save($message);

        $this->emit('message.received', [
            'message' => $message,
            'room' => $target['room'],
            'connection' => $connection,
            'scope' => $target['scope'],
        ]);

        $envelope = MessageEnvelope::server('message.received', [
            'roomId' => $target['room']->id,
            'message' => $message->toArray(),
        ]);

        if ($target['recipientUserIds'] === null) {
            $this->broadcastAuthenticated($connections, $envelope);

            return;
        }

        $this->deliverToUsers($connections, $target['recipientUserIds'], $envelope);
    }

    private function handleMessageRead(
        ConnectionRegistryInterface $connections,
        Connection $connection,
        MessageEnvelope $envelope,
    ): void {
        $userId = $this->requireAuthenticated($connection);
        $session = $this->sessions->findByUserId($userId);

        if (!$session instanceof UserSession) {
            throw new InvalidPayloadException('Connection session was not found.');
        }

        $roomId = $envelope->payload['roomId'] ?? 'global';

        if (!is_string($roomId) || trim($roomId) === '') {
            $roomId = 'global';
        }

        $roomId = trim($roomId);
        $payload = [
            'messageId' => $this->validator->messageId($envelope),
            'roomId' => $roomId,
            'userId' => $userId,
            'displayName' => $session->displayName,
            'readAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];

        if ($roomId === 'global') {
            $this->broadcastAuthenticatedExcept($connections, $userId, MessageEnvelope::server('message.read', $payload));

            return;
        }

        $room = $this->roomManager->assertMember($roomId, $userId);
        $recipientUserIds = array_values(array_filter(
            $room->memberUserIds,
            static fn (string $memberUserId): bool => $memberUserId !== $userId,
        ));

        $this->deliverToUsers($connections, $recipientUserIds, MessageEnvelope::server('message.read', $payload));
    }

    private function handleClose(ConnectionRegistryInterface $connections, Connection $connection): void
    {
        $userId = $connection->userId();

        if ($userId === null) {
            return;
        }

        $session = $this->sessions->findByUserId($userId);

        $this->presence->leave($userId);

        $this->emit('user.left', [
            'userId' => $userId,
            'session' => $session,
            'connection' => $connection,
        ]);

        $this->broadcastAuthenticated($connections, MessageEnvelope::server('typing.stopped', [
            'userId' => $userId,
            'roomId' => 'global',
        ]));

        $this->broadcastAuthenticated($connections, MessageEnvelope::server('presence.user_left', [
            'userId' => $userId,
        ]));
    }

    private function handleTypingStatus(
        ConnectionRegistryInterface $connections,
        Connection $connection,
        MessageEnvelope $envelope,
        string $eventType,
    ): void {
        $userId = $this->requireAuthenticated($connection);
        $session = $this->sessions->findByUserId($userId);

        if (!$session instanceof UserSession) {
            throw new InvalidPayloadException('Connection session was not found.');
        }

        $toUserId = $envelope->payload['toUserId'] ?? null;

        if ($toUserId !== null && !is_string($toUserId)) {
            throw new InvalidPayloadException('Payload field toUserId must be a string.');
        }

        if (is_string($toUserId)) {
            $toUserId = trim($toUserId);

            if ($toUserId === '') {
                $toUserId = null;
            }
        }

        if ($toUserId !== null) {
            $this->assertOnlineUser($toUserId);

            $room = $this->roomManager->createDirectRoom($userId, $toUserId);

            $this->deliverToUsers($connections, [$toUserId], MessageEnvelope::server($eventType, [
                'userId' => $userId,
                'displayName' => $session->displayName,
                'roomId' => $room->id,
                'scope' => 'direct',
                'toUserId' => $toUserId,
            ]));

            return;
        }

        $roomId = $envelope->payload['roomId'] ?? null;

        if ($roomId !== null && !is_string($roomId)) {
            throw new InvalidPayloadException('Payload field roomId must be a string.');
        }

        if (is_string($roomId)) {
            $roomId = trim($roomId);

            if ($roomId !== '' && $roomId !== 'global') {
                $room = $this->roomManager->assertMember($roomId, $userId);
                $recipientUserIds = array_values(array_filter(
                    $room->memberUserIds,
                    static fn (string $memberUserId): bool => $memberUserId !== $userId,
                ));

                $this->deliverToUsers($connections, $recipientUserIds, MessageEnvelope::server($eventType, [
                    'userId' => $userId,
                    'displayName' => $session->displayName,
                    'roomId' => $room->id,
                    'scope' => 'room',
                ]));

                return;
            }
        }

        $this->broadcastAuthenticatedExcept($connections, $userId, MessageEnvelope::server($eventType, [
            'userId' => $userId,
            'displayName' => $session->displayName,
            'roomId' => 'global',
            'scope' => 'global',
        ]));
    }

    /**
     * @return array{room: Room, recipientUserIds: list<string>|null, scope: string}
     */
    private function resolveFileMessageTarget(string $fromUserId, MessageEnvelope $envelope): array
    {
        $scope = $envelope->payload['scope'] ?? 'global';

        if (!is_string($scope) || trim($scope) === '') {
            $scope = 'global';
        }

        $scope = trim($scope);

        if ($scope === 'direct') {
            $toUserId = $this->validator->targetUserId($envelope);

            $this->assertOnlineUser($toUserId);

            $room = $this->roomManager->createDirectRoom($fromUserId, $toUserId);

            return [
                'room' => $room,
                'recipientUserIds' => [$fromUserId, $toUserId],
                'scope' => 'direct',
            ];
        }

        if ($scope === 'room') {
            $roomId = $this->validator->roomId($envelope);
            $room = $this->roomManager->assertMember($roomId, $fromUserId);

            return [
                'room' => $room,
                'recipientUserIds' => $room->memberUserIds,
                'scope' => 'room',
            ];
        }

        return [
            'room' => $this->roomManager->ensureGlobalRoom(),
            'recipientUserIds' => null,
            'scope' => 'global',
        ];
    }

    private function storeAttachment(
        string $messageId,
        string $fileName,
        string $mimeType,
        string $content,
    ): Attachment {
        if ($this->attachments instanceof FileAttachmentStore) {
            return $this->attachments->saveContent(
                messageId: $messageId,
                fileName: $fileName,
                mimeType: $mimeType,
                content: $content,
            );
        }

        return $this->attachments->save(Attachment::new(
            messageId: $messageId,
            fileName: $fileName,
            mimeType: $mimeType,
            sizeBytes: strlen($content),
            path: 'attachment://' . $fileName,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function fileMessageBody(Attachment $attachment, string $content, string $caption): array
    {
        return [
            'attachmentId' => $attachment->id,
            'fileName' => $attachment->fileName,
            'mimeType' => $attachment->mimeType,
            'sizeBytes' => $attachment->sizeBytes,
            'previewDataUrl' => $this->previewDataUrl($attachment->mimeType, $content),
            'downloadDataUrl' => 'data:' . $attachment->mimeType . ';base64,' . base64_encode($content),
            'caption' => $caption,
        ];
    }

    private function previewDataUrl(string $mimeType, string $content): ?string
    {
        if (!str_starts_with($mimeType, 'image/')) {
            return null;
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($content);
    }

    /**
     * @param list<string>|null $recipientUserIds
     */
    private function dispatchBotResponses(
        ConnectionRegistryInterface $connections,
        ChatMessage $sourceMessage,
        Room $room,
        ?Connection $connection,
        string $scope,
        ?array $recipientUserIds,
    ): void {
        if (!$this->bots->hasBots()) {
            return;
        }

        if ($sourceMessage->kind !== 'text') {
            return;
        }

        if (!is_string($sourceMessage->body) || trim($sourceMessage->body) === '') {
            return;
        }

        $sender = $this->sessions->findByUserId($sourceMessage->fromUserId);
        $context = new BotContext(
            message: $sourceMessage,
            room: $room,
            sender: $sender,
            scope: $scope,
            recipientUserIds: $recipientUserIds ?? [],
        );

        foreach ($this->bots->handle($context) as $botResult) {
            $bot = $botResult['bot'];
            $response = $botResult['response'];
            $botMessage = ChatMessage::bot(
                roomId: $room->id,
                botName: $bot->name(),
                text: $response->text,
                metadata: $response->metadata,
            );

            $this->messages->save($botMessage);

            $this->emit('bot.responded', [
                'bot' => $bot,
                'message' => $botMessage,
                'room' => $room,
                'sourceMessage' => $sourceMessage,
                'connection' => $connection,
                'scope' => $scope,
            ]);

            $envelope = MessageEnvelope::server('message.received', [
                'roomId' => $room->id,
                'message' => $botMessage->toArray(),
            ]);

            if ($recipientUserIds === null) {
                $this->broadcastAuthenticated($connections, $envelope);

                continue;
            }

            $this->deliverToUsers($connections, $recipientUserIds, $envelope);
        }
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

    private function sendSessionRejected(Connection $connection, string $reason, string $message): void
    {
        $this->sendEnvelope($connection, MessageEnvelope::server('session.rejected', [
            'reason' => $reason,
            'message' => $message,
        ]));
    }

    private function sendEnvelope(Connection $connection, MessageEnvelope $envelope): void
    {
        $connection->send($envelope->toJson());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function emit(string $eventName, array $payload): void
    {
        foreach ($this->listeners[$eventName] ?? [] as $listener) {
            $listener($payload);
        }
    }

    private function broadcastAuthenticated(ConnectionRegistryInterface $connections, MessageEnvelope $envelope): void
    {
        foreach ($connections->all() as $connection) {
            if ($connection->userId() !== null) {
                $this->sendEnvelope($connection, $envelope);
            }
        }
    }

    private function broadcastAuthenticatedExcept(
        ConnectionRegistryInterface $connections,
        string $exceptUserId,
        MessageEnvelope $envelope,
    ): void {
        foreach ($connections->all() as $connection) {
            $connectionUserId = $connection->userId();

            if ($connectionUserId !== null && $connectionUserId !== $exceptUserId) {
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
