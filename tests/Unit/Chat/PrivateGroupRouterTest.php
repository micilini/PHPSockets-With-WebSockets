<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Chat;

use Micilini\PhpSockets\Chat\PrivateGroupRouter;
use Micilini\PhpSockets\Chat\Room;
use Micilini\PhpSockets\Chat\RoomManager;
use Micilini\PhpSockets\Exceptions\InvalidPayloadException;
use Micilini\PhpSockets\Exceptions\RoomAccessDeniedException;
use Micilini\PhpSockets\Storage\InMemory\InMemoryMessageStore;
use Micilini\PhpSockets\Storage\InMemory\InMemoryRoomStore;
use PHPUnit\Framework\TestCase;

final class PrivateGroupRouterTest extends TestCase
{
    public function testPrivateGroupRoomIncludesCreatorAndParticipants(): void
    {
        $router = $this->router();

        $room = $router->createRoom(
            createdByUserId: 'usr_william',
            name: 'Project team',
            participantUserIds: ['usr_ana', 'usr_bruno'],
            maxMembers: 4,
        );

        self::assertSame(Room::TYPE_PRIVATE_GROUP, $room->type);
        self::assertSame('Project team', $room->name);
        self::assertSame(['usr_william', 'usr_ana', 'usr_bruno'], $room->memberUserIds);
    }

    public function testPrivateGroupRoomRejectsMissingParticipants(): void
    {
        $router = $this->router();

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Private group room requires at least one participant.');

        $router->createRoom(
            createdByUserId: 'usr_william',
            name: null,
            participantUserIds: [],
            maxMembers: 4,
        );
    }

    public function testPrivateGroupRoomRespectsMemberLimit(): void
    {
        $router = $this->router();

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Private group room member limit exceeded.');

        $router->createRoom(
            createdByUserId: 'usr_william',
            name: null,
            participantUserIds: ['usr_ana', 'usr_bruno'],
            maxMembers: 2,
        );
    }

    public function testRoomMessagePreservesClientMessageId(): void
    {
        $messageStore = new InMemoryMessageStore();
        $router = $this->router($messageStore);
        $room = $router->createRoom(
            createdByUserId: 'usr_william',
            name: null,
            participantUserIds: ['usr_ana'],
            maxMembers: 4,
        );

        $message = $router->send(
            roomId: $room->id,
            fromUserId: 'usr_william',
            text: 'Hello room',
            metadata: ['clientMessageId' => 'client_room_123'],
        );

        self::assertSame($room->id, $message->roomId);
        self::assertSame('client_room_123', $message->metadata['clientMessageId'] ?? null);
        self::assertSame([$message], $messageStore->messagesForRoom($room->id));
    }

    public function testOutsideUserCannotSendRoomMessage(): void
    {
        $router = $this->router();
        $room = $router->createRoom(
            createdByUserId: 'usr_william',
            name: null,
            participantUserIds: ['usr_ana'],
            maxMembers: 4,
        );

        $this->expectException(RoomAccessDeniedException::class);

        $router->send($room->id, 'usr_carla', 'No access');
    }

    private function router(?InMemoryMessageStore $messageStore = null): PrivateGroupRouter
    {
        return new PrivateGroupRouter(
            rooms: new RoomManager(new InMemoryRoomStore()),
            messages: $messageStore ?? new InMemoryMessageStore(),
        );
    }
}
