<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use DateTimeImmutable;

final readonly class Attachment
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public string $messageId,
        public string $fileName,
        public string $mimeType,
        public int $sizeBytes,
        public string $path,
        public DateTimeImmutable $createdAt,
        public array $metadata = [],
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function new(
        string $messageId,
        string $fileName,
        string $mimeType,
        int $sizeBytes,
        string $path,
        array $metadata = [],
    ): self {
        return new self(
            id: 'att_' . bin2hex(random_bytes(16)),
            messageId: $messageId,
            fileName: $fileName,
            mimeType: $mimeType,
            sizeBytes: $sizeBytes,
            path: $path,
            createdAt: new DateTimeImmutable(),
            metadata: $metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        return [
            'id' => $this->id,
            'messageId' => $this->messageId,
            'fileName' => $this->fileName,
            'mimeType' => $this->mimeType,
            'sizeBytes' => $this->sizeBytes,
            'path' => $this->path,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'metadata' => $this->metadata,
        ];
    }
}
