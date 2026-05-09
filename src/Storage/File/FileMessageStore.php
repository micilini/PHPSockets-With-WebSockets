<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Storage\File;

use DateTimeImmutable;
use Micilini\PhpSockets\Chat\ChatMessage;
use Micilini\PhpSockets\Contracts\MessageStoreInterface;
use Micilini\PhpSockets\Exceptions\StorageException;

final readonly class FileMessageStore implements MessageStoreInterface
{
    public function __construct(private string $filePath)
    {
        $directory = dirname($this->filePath);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new StorageException("Message storage directory cannot be created: {$directory}");
        }

        if (is_file($this->filePath) && !is_writable($this->filePath)) {
            throw new StorageException("Message storage file is not writable: {$this->filePath}");
        }

        if (!is_file($this->filePath) && !is_writable($directory)) {
            throw new StorageException("Message storage directory is not writable: {$directory}");
        }
    }

    public function save(ChatMessage $message): void
    {
        $handle = fopen($this->filePath, 'ab');

        if ($handle === false) {
            throw new StorageException("Message storage file cannot be opened: {$this->filePath}");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new StorageException("Message storage file cannot be locked: {$this->filePath}");
            }

            $line = json_encode($message->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;

            if (fwrite($handle, $line) === false) {
                throw new StorageException("Message storage file cannot be written: {$this->filePath}");
            }

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    public function messagesForRoom(string $roomId, int $limit = 50): array
    {
        if ($limit <= 0 || !is_file($this->filePath)) {
            return [];
        }

        $handle = fopen($this->filePath, 'rb');

        if ($handle === false) {
            throw new StorageException("Message storage file cannot be opened: {$this->filePath}");
        }

        $messages = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $row = json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR);

                if (!is_array($row) || ($row['roomId'] ?? null) !== $roomId) {
                    continue;
                }

                $messages[] = $this->hydrate($row);
            }
        } finally {
            fclose($handle);
        }

        return array_slice($messages, -$limit);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ChatMessage
    {
        $metadata = $row['metadata'] ?? [];

        if (!is_array($metadata)) {
            $metadata = [];
        }

        /** @var array<string, mixed> $metadata */
        return new ChatMessage(
            id: (string) $row['id'],
            roomId: (string) $row['roomId'],
            fromUserId: (string) $row['fromUserId'],
            kind: (string) $row['kind'],
            body: $row['body'] === null ? null : (string) $row['body'],
            createdAt: new DateTimeImmutable((string) $row['createdAt']),
            metadata: $metadata,
        );
    }
}
