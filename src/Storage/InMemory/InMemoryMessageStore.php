<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Storage\InMemory;

use Micilini\PhpSockets\Chat\ChatMessage;
use Micilini\PhpSockets\Contracts\MessageStoreInterface;

final class InMemoryMessageStore implements MessageStoreInterface
{
    /**
     * @var array<string, list<ChatMessage>>
     */
    private array $messagesByRoomId = [];

    public function save(ChatMessage $message): void
    {
        $this->messagesByRoomId[$message->roomId] ??= [];
        $this->messagesByRoomId[$message->roomId][] = $message;
    }

    public function messagesForRoom(string $roomId, int $limit = 50): array
    {
        $messages = $this->messagesByRoomId[$roomId] ?? [];

        if ($limit < 1) {
            return [];
        }

        return array_slice($messages, -$limit);
    }
}
