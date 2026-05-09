<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use Micilini\PhpSockets\Contracts\MessageStoreInterface;

final readonly class PrivateGroupRouter
{
    public function __construct(
        private RoomManager $rooms,
        private MessageStoreInterface $messages,
    ) {
    }

    /**
     * @param list<string> $participantUserIds
     */
    public function createRoom(
        string $createdByUserId,
        ?string $name,
        array $participantUserIds,
        int $maxMembers,
    ): Room {
        return $this->rooms->createPrivateGroupRoom(
            createdByUserId: $createdByUserId,
            name: $name,
            participantUserIds: $participantUserIds,
            maxMembers: $maxMembers,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function send(string $roomId, string $fromUserId, string $text, array $metadata = []): ChatMessage
    {
        $room = $this->rooms->assertMember($roomId, $fromUserId);
        $message = ChatMessage::text($room->id, $fromUserId, $text, $metadata);

        $this->messages->save($message);

        return $message;
    }
}
