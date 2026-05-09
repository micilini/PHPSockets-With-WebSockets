<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Storage\Pdo;

use DateTimeImmutable;
use Micilini\PhpSockets\Chat\Room;
use Micilini\PhpSockets\Contracts\RoomStoreInterface;
use Micilini\PhpSockets\Exceptions\StorageException;
use PDO;
use PDOStatement;

final readonly class PdoRoomStore implements RoomStoreInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(Room $room): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->saveRoom($room);
            $this->syncMembers($room);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }

    public function find(string $roomId): ?Room
    {
        $statement = $this->prepare(
            'SELECT id, type, name, created_by, created_at
             FROM rooms
             WHERE id = :id
             LIMIT 1',
        );
        $statement->execute(['id' => $roomId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function all(): array
    {
        $statement = $this->prepare(
            'SELECT id, type, name, created_by, created_at
             FROM rooms
             ORDER BY created_at ASC',
        );
        $statement->execute();
        $rows = $statement->fetchAll();

        return array_values(array_map(fn (array $row): Room => $this->hydrate($row), $rows));
    }

    public function visibleForUser(string $userId): array
    {
        $statement = $this->prepare(
            'SELECT r.id, r.type, r.name, r.created_by, r.created_at
             FROM rooms r
             LEFT JOIN room_members rm ON rm.room_id = r.id AND rm.user_id = :user_id
             WHERE r.type = :global_type OR rm.user_id IS NOT NULL
             ORDER BY r.created_at ASC',
        );
        $statement->execute([
            'user_id' => $userId,
            'global_type' => Room::TYPE_GLOBAL,
        ]);
        $rows = $statement->fetchAll();

        return array_values(array_map(fn (array $row): Room => $this->hydrate($row), $rows));
    }

    public function addMember(string $roomId, string $userId): void
    {
        if (!$this->roomExists($roomId) || $this->memberExists($roomId, $userId)) {
            return;
        }

        $statement = $this->prepare(
            'INSERT INTO room_members (room_id, user_id, joined_at)
             VALUES (:room_id, :user_id, :joined_at)',
        );
        $statement->execute([
            'room_id' => $roomId,
            'user_id' => $userId,
            'joined_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    public function removeMember(string $roomId, string $userId): void
    {
        $statement = $this->prepare(
            'DELETE FROM room_members
             WHERE room_id = :room_id AND user_id = :user_id',
        );
        $statement->execute([
            'room_id' => $roomId,
            'user_id' => $userId,
        ]);
    }

    private function saveRoom(Room $room): void
    {
        if ($this->roomExists($room->id)) {
            $statement = $this->prepare(
                'UPDATE rooms
                 SET type = :type, name = :name, created_by = :created_by, created_at = :created_at
                 WHERE id = :id',
            );
        } else {
            $statement = $this->prepare(
                'INSERT INTO rooms (id, type, name, created_by, created_at)
                 VALUES (:id, :type, :name, :created_by, :created_at)',
            );
        }

        $statement->execute([
            'id' => $room->id,
            'type' => $room->type,
            'name' => $room->name,
            'created_by' => $room->createdBy,
            'created_at' => $room->createdAt->format(DATE_ATOM),
        ]);
    }

    private function syncMembers(Room $room): void
    {
        $delete = $this->prepare('DELETE FROM room_members WHERE room_id = :room_id');
        $delete->execute(['room_id' => $room->id]);

        foreach ($room->memberUserIds as $userId) {
            $this->addMember($room->id, $userId);
        }
    }

    private function roomExists(string $roomId): bool
    {
        $statement = $this->prepare('SELECT 1 FROM rooms WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $roomId]);

        return is_array($statement->fetch());
    }

    private function memberExists(string $roomId, string $userId): bool
    {
        $statement = $this->prepare(
            'SELECT 1
             FROM room_members
             WHERE room_id = :room_id AND user_id = :user_id
             LIMIT 1',
        );
        $statement->execute([
            'room_id' => $roomId,
            'user_id' => $userId,
        ]);

        return is_array($statement->fetch());
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Room
    {
        return new Room(
            id: (string) $row['id'],
            type: (string) $row['type'],
            name: $row['name'] === null ? null : (string) $row['name'],
            createdBy: (string) $row['created_by'],
            memberUserIds: $this->memberUserIds((string) $row['id']),
            createdAt: new DateTimeImmutable((string) $row['created_at']),
        );
    }

    /**
     * @return list<string>
     */
    private function memberUserIds(string $roomId): array
    {
        $statement = $this->prepare(
            'SELECT user_id
             FROM room_members
             WHERE room_id = :room_id
             ORDER BY joined_at ASC',
        );
        $statement->execute(['room_id' => $roomId]);
        $rows = $statement->fetchAll();

        return array_values(array_map(static fn (array $row): string => (string) $row['user_id'], $rows));
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
