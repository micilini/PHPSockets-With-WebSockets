<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use Micilini\PhpSockets\Contracts\MessageStoreInterface;

final readonly class DirectMessageRouter
{
    public function __construct(
        private RoomManager $rooms,
        private MessageStoreInterface $messages,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function send(string $fromUserId, string $toUserId, string $text, array $metadata = []): ChatMessage
    {
        $room = $this->rooms->createDirectRoom($fromUserId, $toUserId);
        $message = ChatMessage::text($room->id, $fromUserId, $text, $metadata);

        $this->messages->save($message);

        return $message;
    }
}
