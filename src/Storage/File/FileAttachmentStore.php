<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Storage\File;

use DateTimeImmutable;
use Micilini\PhpSockets\Chat\Attachment;
use Micilini\PhpSockets\Contracts\AttachmentStoreInterface;
use Micilini\PhpSockets\Exceptions\StorageException;

final readonly class FileAttachmentStore implements AttachmentStoreInterface
{
    public function __construct(private string $basePath)
    {
        $this->ensureDirectory($this->basePath);
    }

    public function save(Attachment $attachment): Attachment
    {
        $metadataPath = $this->metadataPath($attachment->id);
        $metadata = json_encode($attachment->publicPayload(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (file_put_contents($metadataPath, $metadata, LOCK_EX) === false) {
            throw new StorageException('Failed to save attachment metadata.');
        }

        return $attachment;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function saveContent(
        string $messageId,
        string $fileName,
        string $mimeType,
        string $content,
        array $metadata = [],
    ): Attachment {
        $attachment = Attachment::new(
            messageId: $messageId,
            fileName: $fileName,
            mimeType: $mimeType,
            sizeBytes: strlen($content),
            path: '',
            metadata: $metadata,
        );

        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $physicalName = $attachment->id . ($extension !== '' ? '.' . $extension : '');
        $filePath = $this->basePath . DIRECTORY_SEPARATOR . $physicalName;

        if (file_put_contents($filePath, $content, LOCK_EX) === false) {
            throw new StorageException('Failed to save attachment content.');
        }

        $attachment = new Attachment(
            id: $attachment->id,
            messageId: $attachment->messageId,
            fileName: $attachment->fileName,
            mimeType: $attachment->mimeType,
            sizeBytes: $attachment->sizeBytes,
            path: $filePath,
            createdAt: $attachment->createdAt,
            metadata: $attachment->metadata,
        );

        return $this->save($attachment);
    }

    public function find(string $attachmentId): ?Attachment
    {
        $metadataPath = $this->metadataPath($attachmentId);

        if (!is_file($metadataPath)) {
            return null;
        }

        $json = file_get_contents($metadataPath);

        if (!is_string($json)) {
            return null;
        }

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload)) {
            return null;
        }

        $metadata = $payload['metadata'] ?? [];

        if (!is_array($metadata)) {
            $metadata = [];
        }

        /** @var array<string, mixed> $metadata */
        return new Attachment(
            id: (string) $payload['id'],
            messageId: (string) $payload['messageId'],
            fileName: (string) $payload['fileName'],
            mimeType: (string) $payload['mimeType'],
            sizeBytes: (int) $payload['sizeBytes'],
            path: (string) $payload['path'],
            createdAt: new DateTimeImmutable((string) $payload['createdAt']),
            metadata: $metadata,
        );
    }

    private function metadataPath(string $attachmentId): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . $attachmentId . '.json';
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            if (!is_writable($path)) {
                throw new StorageException("Attachment directory is not writable: {$path}");
            }

            return;
        }

        if (file_exists($path)) {
            throw new StorageException("Attachment path exists but is not a directory: {$path}");
        }

        if (!@mkdir($path, 0775, true) && !is_dir($path)) {
            throw new StorageException("Failed to create attachment directory: {$path}");
        }

        if (!is_writable($path)) {
            throw new StorageException("Attachment directory is not writable: {$path}");
        }
    }
}
