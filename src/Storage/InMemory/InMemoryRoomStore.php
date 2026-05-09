<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Storage\InMemory;

use Micilini\PhpSockets\Chat\Room;
use Micilini\PhpSockets\Contracts\RoomStoreInterface;

final class InMemoryRoomStore implements RoomStoreInterface
{
    /**
     * @var array<string, Room>
     */
    private array $roomsById = [];

    public function save(Room $room): void
    {
        $this->roomsById[$room->id] = $room;
    }

    public function find(string $roomId): ?Room
    {
        return $this->roomsById[$roomId] ?? null;
    }

    public function all(): array
    {
        return array_values($this->roomsById);
    }

    public function visibleForUser(string $userId): array
    {
        return array_values(array_filter(
            $this->roomsById,
            static fn (Room $room): bool => $room->hasMember($userId),
        ));
    }

    public function addMember(string $roomId, string $userId): void
    {
        $room = $this->find($roomId);

        if (!$room instanceof Room) {
            return;
        }

        $this->save($room->withMember($userId));
    }

    public function removeMember(string $roomId, string $userId): void
    {
        $room = $this->find($roomId);

        if (!$room instanceof Room) {
            return;
        }

        $this->save($room->withoutMember($userId));
    }
}
