<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Contracts;

use Micilini\PhpSockets\Chat\ChatMessage;

interface MessageStoreInterface
{
    public function save(ChatMessage $message): void;

    /**
     * @return list<ChatMessage>
     */
    public function messagesForRoom(string $roomId, int $limit = 50): array;
}
