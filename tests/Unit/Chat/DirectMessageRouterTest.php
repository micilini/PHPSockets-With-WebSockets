<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Chat;

use Micilini\PhpSockets\Chat\DirectMessageRouter;
use Micilini\PhpSockets\Chat\RoomManager;
use Micilini\PhpSockets\Storage\InMemory\InMemoryMessageStore;
use Micilini\PhpSockets\Storage\InMemory\InMemoryRoomStore;
use PHPUnit\Framework\TestCase;

final class DirectMessageRouterTest extends TestCase
{
    public function testDirectRoomIsStableAndMessageMetadataIsPreserved(): void
    {
        $roomStore = new InMemoryRoomStore();
        $messageStore = new InMemoryMessageStore();
        $roomManager = new RoomManager($roomStore);
        $router = new DirectMessageRouter($roomManager, $messageStore);

        $firstMessage = $router->send(
            fromUserId: 'usr_william',
            toUserId: 'usr_ana',
            text: 'Hello Ana',
            metadata: ['clientMessageId' => 'client_123'],
        );
        $secondMessage = $router->send(
            fromUserId: 'usr_ana',
            toUserId: 'usr_william',
            text: 'Hello William',
        );

        self::assertSame($firstMessage->roomId, $secondMessage->roomId);
        self::assertSame('client_123', $firstMessage->metadata['clientMessageId'] ?? null);

        $room = $roomStore->find($firstMessage->roomId);

        self::assertNotNull($room);
        self::assertSame(['usr_ana', 'usr_william'], $room->memberUserIds);
        self::assertSame([$firstMessage, $secondMessage], $messageStore->messagesForRoom($firstMessage->roomId));
    }
}
