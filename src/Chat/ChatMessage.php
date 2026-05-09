<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use DateTimeImmutable;

final readonly class ChatMessage
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public string $roomId,
        public string $fromUserId,
        public string $kind,
        public ?string $body,
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
