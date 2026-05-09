<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat\Bot;

use Micilini\PhpSockets\Chat\ChatMessage;
use Micilini\PhpSockets\Chat\Room;
use Micilini\PhpSockets\Chat\UserSession;

final readonly class BotContext
{
    /**
     * @param list<string> $recipientUserIds
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public ChatMessage $message,
        public Room $room,
        public ?UserSession $sender,
        public string $scope,
        public array $recipientUserIds,
        public array $metadata = [],
    ) {
    }

    public function text(): string
    {
        return is_string($this->message->body) ? $this->message->body : '';
    }

    public function senderDisplayName(): string
    {
        return $this->sender instanceof UserSession ? $this->sender->displayName : 'Unknown user';
    }

    public function isGlobal(): bool
    {
        return $this->scope === 'global';
    }

    public function isDirect(): bool
    {
        return $this->scope === 'direct';
    }

    public function isRoom(): bool
    {
        return $this->scope === 'room';
    }
}
