<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use DateTimeImmutable;

final readonly class ChatMessage
{
    /**
     * @param string|array<string, mixed>|null $body
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public string $roomId,
        public string $fromUserId,
        public string $kind,
        public string|array|null $body,
        public DateTimeImmutable $createdAt,
        public array $metadata = [],
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function text(string $roomId, string $fromUserId, string $text, array $metadata = []): self
    {
        return new self(
            id: 'msg_' . bin2hex(random_bytes(16)),
            roomId: $roomId,
            fromUserId: $fromUserId,
            kind: 'text',
            body: $text,
            createdAt: new DateTimeImmutable(),
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $metadata
     */
    public static function file(string $roomId, string $fromUserId, array $body, array $metadata = []): self
    {
        return new self(
            id: 'msg_' . bin2hex(random_bytes(16)),
            roomId: $roomId,
            fromUserId: $fromUserId,
            kind: 'file',
            body: $body,
            createdAt: new DateTimeImmutable(),
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function bot(string $roomId, string $botName, string $text, array $metadata = []): self
    {
        $normalizedName = preg_replace('/[^a-zA-Z0-9_ -]/', '_', $botName);

        if (!is_string($normalizedName) || trim($normalizedName) === '') {
            $normalizedName = 'bot';
        }

        $botId = 'bot:' . strtolower(str_replace(' ', '_', trim($normalizedName)));

        return new self(
            id: 'msg_' . bin2hex(random_bytes(16)),
            roomId: $roomId,
            fromUserId: $botId,
            kind: 'bot',
            body: $text,
            createdAt: new DateTimeImmutable(),
            metadata: [
                'bot' => true,
                'botName' => $botName,
                ...$metadata,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'roomId' => $this->roomId,
            'fromUserId' => $this->fromUserId,
            'kind' => $this->kind,
            'body' => $this->body,
            'metadata' => $this->metadata,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
