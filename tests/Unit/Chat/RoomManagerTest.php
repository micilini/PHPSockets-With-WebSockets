<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Chat;

use Micilini\PhpSockets\Chat\Room;
use Micilini\PhpSockets\Chat\RoomManager;
use Micilini\PhpSockets\Exceptions\InvalidPayloadException;
use Micilini\PhpSockets\Exceptions\RoomAccessDeniedException;
use Micilini\PhpSockets\Storage\InMemory\InMemoryRoomStore;
use PHPUnit\Framework\TestCase;

final class RoomManagerTest extends TestCase
{
    public function testGlobalRoomIsCreatedOnce(): void
    {
        $store = new InMemoryRoomStore();
        $manager = new RoomManager($store);

        $firstRoom = $manager->ensureGlobalRoom();
        $secondRoom = $manager->ensureGlobalRoom();

        self::assertSame('global', $firstRoom->id);
        self::assertSame($firstRoom, $secondRoom);
        self::assertSame(1, count($store->all()));
    }

    public function testDirectRoomUsesSameIdForSameUsers(): void
    {
        $manager = new RoomManager(new InMemoryRoomStore());

        $firstRoom = $manager->createDirectRoom('usr_a', 'usr_b');
        $secondRoom = $manager->createDirectRoom('usr_b', 'usr_a');

        self::assertSame($firstRoom->id, $secondRoom->id);
        self::assertSame(Room::TYPE_DIRECT, $firstRoom->type);
        self::assertSame(['usr_a', 'usr_b'], $firstRoom->memberUserIds);
    }

    public function testPrivateGroupRoomIncludesCreator(): void
    {
        $manager = new RoomManager(new InMemoryRoomStore());

        $room = $manager->createPrivateGroupRoom(
            createdByUserId: 'usr_creator',
            name: 'Secret room',
            participantUserIds: ['usr_a', 'usr_b'],
            maxMembers: 5,
        );

        self::assertSame(Room::TYPE_PRIVATE_GROUP, $room->type);
        self::assertSame('Secret room', $room->name);
        self::assertSame(['usr_creator', 'usr_a', 'usr_b'], $room->memberUserIds);
    }

    public function testPrivateGroupMemberLimitIsValidated(): void
    {
        $manager = new RoomManager(new InMemoryRoomStore());

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Private group room member limit exceeded.');

        $manager->createPrivateGroupRoom(
            createdByUserId: 'usr_creator',
            name: null,
            participantUserIds: ['usr_a', 'usr_b'],
            maxMembers: 2,
        );
    }

    public function testRoomAccessIsValidated(): void
    {
        $manager = new RoomManager(new InMemoryRoomStore());

        $room = $manager->createPrivateGroupRoom(
            createdByUserId: 'usr_creator',
            name: null,
            participantUserIds: ['usr_a'],
            maxMembers: 5,
        );

        $this->expectException(RoomAccessDeniedException::class);
        $this->expectExceptionMessage('User is not a member of this room.');

        $manager->assertMember($room->id, 'usr_outside');
    }
}
