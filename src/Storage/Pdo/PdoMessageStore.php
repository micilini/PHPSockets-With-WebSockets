<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Storage\Pdo;

use DateTimeImmutable;
use Micilini\PhpSockets\Chat\ChatMessage;
use Micilini\PhpSockets\Contracts\MessageStoreInterface;
use Micilini\PhpSockets\Exceptions\StorageException;
use PDO;
use PDOStatement;

final readonly class PdoMessageStore implements MessageStoreInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(ChatMessage $message): void
    {
        $statement = $this->prepare(
            'INSERT INTO messages (id, room_id, from_user_id, kind, body, metadata_json, created_at)
             VALUES (:id, :room_id, :from_user_id, :kind, :body, :metadata_json, :created_at)',
        );
        $statement->execute([
            'id' => $message->id,
            'room_id' => $message->roomId,
            'from_user_id' => $message->fromUserId,
            'kind' => $message->kind,
            'body' => $message->body,
            'metadata_json' => json_encode($message->metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => $message->createdAt->format(DATE_ATOM),
        ]);
    }

    public function messagesForRoom(string $roomId, int $limit = 50): array
    {
        if ($limit <= 0) {
            return [];
        }

        $statement = $this->prepare(
            'SELECT id, room_id, from_user_id, kind, body, metadata_json, created_at
             FROM messages
             WHERE room_id = :room_id
             ORDER BY created_at DESC
             LIMIT :limit',
        );
        $statement->bindValue(':room_id', $roomId);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();

        $messages = array_map(fn (array $row): ChatMessage => $this->hydrate($row), $rows);

        return array_values(array_reverse($messages));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ChatMessage
    {
        return new ChatMessage(
            id: (string) $row['id'],
            roomId: (string) $row['room_id'],
            fromUserId: (string) $row['from_user_id'],
            kind: (string) $row['kind'],
            body: $row['body'] === null ? null : (string) $row['body'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            metadata: $this->decodeMetadata((string) $row['metadata_json']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMetadata(string $json): array
    {
        $metadata = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($metadata)) {
            return [];
        }

        /** @var array<string, mixed> $metadata */
        return $metadata;
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);

        if (!$statement instanceof PDOStatement) {
            throw new StorageException('Failed to prepare SQL statement.');
        }

        return $statement;
    }
}
