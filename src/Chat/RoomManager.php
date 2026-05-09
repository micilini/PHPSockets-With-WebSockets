<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use Micilini\PhpSockets\Contracts\RoomStoreInterface;
use Micilini\PhpSockets\Exceptions\InvalidPayloadException;
use Micilini\PhpSockets\Exceptions\RoomAccessDeniedException;

final readonly class RoomManager
{
    public function __construct(private RoomStoreInterface $rooms)
    {
    }

    public function ensureGlobalRoom(): Room
    {
        $existing = $this->rooms->find('global');

        if ($existing instanceof Room) {
            return $existing;
        }

        $room = Room::global();

        $this->rooms->save($room);

        return $room;
    }

    public function joinGlobalRoom(string $userId): Room
    {
        $room = $this->ensureGlobalRoom();
        $this->rooms->addMember($room->id, $userId);

        return $this->rooms->find($room->id) ?? $room;
    }

    public function createDirectRoom(string $firstUserId, string $secondUserId): Room
    {
        if ($firstUserId === $secondUserId) {
            throw new InvalidPayloadException('Direct room requires two different users.');
        }

        $memberUserIds = [$firstUserId, $secondUserId];
        sort($memberUserIds);

        $roomId = 'direct_' . sha1($memberUserIds[0] . '|' . $memberUserIds[1]);
        $existing = $this->rooms->find($roomId);

        if ($existing instanceof Room) {
            return $existing;
        }

        $room = Room::direct($roomId, $memberUserIds, $firstUserId);

        $this->rooms->save($room);

        return $room;
    }

    /**
     * @param list<string> $participantUserIds
     */
    public function createPrivateGroupRoom(
        string $createdByUserId,
        ?string $name,
        array $participantUserIds,
        int $maxMembers,
    ): Room {
        $memberUserIds = array_values(array_unique([$createdByUserId, ...$participantUserIds]));

        if (count($memberUserIds) < 2) {
            throw new InvalidPayloadException('Private group room requires at least one participant.');
        }

        if (count($memberUserIds) > $maxMembers) {
            throw new InvalidPayloadException('Private group room member limit exceeded.');
        }

        $room = Room::privateGroup($name ?? '', $createdByUserId, $memberUserIds);

        $this->rooms->save($room);

        return $room;
    }

    public function assertMember(string $roomId, string $userId): Room
    {
        $room = $this->rooms->find($roomId);

        if (!$room instanceof Room) {
            throw new InvalidPayloadException('Room not found.');
        }

        if (!$room->hasMember($userId)) {
            throw new RoomAccessDeniedException('User is not a member of this room.');
        }

        return $room;
    }

    /**
     * @return list<Room>
     */
    public function visibleForUser(string $userId): array
    {
        return $this->rooms->visibleForUser($userId);
    }
}
