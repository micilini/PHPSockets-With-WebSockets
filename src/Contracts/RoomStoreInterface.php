<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Contracts;

use Micilini\PhpSockets\Chat\Room;

interface RoomStoreInterface
{
    public function save(Room $room): void;

    public function find(string $roomId): ?Room;

    /**
     * @return list<Room>
     */
    public function all(): array;

    /**
     * @return list<Room>
     */
    public function visibleForUser(string $userId): array;

    public function addMember(string $roomId, string $userId): void;

    public function removeMember(string $roomId, string $userId): void;
}
