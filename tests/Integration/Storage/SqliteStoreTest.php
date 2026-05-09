<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Integration\Storage;

use DateTimeImmutable;
use Micilini\PhpSockets\Chat\ChatMessage;
use Micilini\PhpSockets\Chat\Room;
use Micilini\PhpSockets\Chat\UserSession;
use Micilini\PhpSockets\Database\MigrationRunner;
use Micilini\PhpSockets\Storage\Pdo\PdoConnectionFactory;
use Micilini\PhpSockets\Storage\Pdo\PdoMessageStore;
use Micilini\PhpSockets\Storage\Pdo\PdoRoomStore;
use Micilini\PhpSockets\Storage\Pdo\PdoSessionStore;
use PHPUnit\Framework\TestCase;

final class SqliteStoreTest extends TestCase
{
    public function testSqliteStoresPersistSessionsRoomsAndMessages(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite extension is not available.');
        }

        $pdo = PdoConnectionFactory::sqliteMemory();

        (new MigrationRunner($pdo))->run('sqlite');

        $sessions = new PdoSessionStore($pdo);
        $rooms = new PdoRoomStore($pdo);
        $messages = new PdoMessageStore($pdo);

        $william = UserSession::create('William', 'william');
        $ana = UserSession::create('Ana', 'ana');
        $bruno = UserSession::create('Bruno', 'bruno');

        $sessions->save($william);
        $sessions->save($ana);
        $sessions->save($bruno);

        self::assertSame($william->userId, $sessions->findBySessionId($william->sessionId)?->userId);
        self::assertSame($ana->sessionId, $sessions->findByUserId($ana->userId)?->sessionId);
        self::assertSame($bruno->userId, $sessions->findConnectedByNormalizedDisplayName('bruno')?->userId);
        self::assertCount(3, $sessions->connected());

        $global = new Room('global', Room::TYPE_GLOBAL, 'Global', 'system', [], new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $direct = new Room(
            'direct_william_ana',
            Room::TYPE_DIRECT,
            null,
            $william->userId,
            [$william->userId, $ana->userId],
            new DateTimeImmutable('2026-01-01T00:00:01+00:00'),
        );
        $group = new Room(
            'room_project',
            Room::TYPE_PRIVATE_GROUP,
            'Project',
            $william->userId,
            [$william->userId, $ana->userId, $bruno->userId],
            new DateTimeImmutable('2026-01-01T00:00:02+00:00'),
        );

        $rooms->save($global);
        $rooms->save($direct);
        $rooms->save($group);

        $messages->save(ChatMessage::text(
            roomId: $global->id,
            fromUserId: $william->userId,
            text: 'Hello global',
            metadata: ['clientMessageId' => 'client_global'],
        ));
        $messages->save(ChatMessage::text(
            roomId: $direct->id,
            fromUserId: $william->userId,
            text: 'Hello Ana',
            metadata: ['clientMessageId' => 'client_direct'],
        ));
        $messages->save(ChatMessage::text(
            roomId: $group->id,
            fromUserId: $bruno->userId,
            text: 'Hello group',
            metadata: ['clientMessageId' => 'client_group'],
        ));

        $globalMessages = $messages->messagesForRoom($global->id);
        $directMessages = $messages->messagesForRoom($direct->id);
        $groupMessages = $messages->messagesForRoom($group->id);

        self::assertSame('client_global', $globalMessages[0]->metadata['clientMessageId'] ?? null);
        self::assertSame('client_direct', $directMessages[0]->metadata['clientMessageId'] ?? null);
        self::assertSame('client_group', $groupMessages[0]->metadata['clientMessageId'] ?? null);

        self::assertSame(['global', 'direct_william_ana', 'room_project'], $this->roomIds($rooms->visibleForUser($william->userId)));
        self::assertSame(['global', 'direct_william_ana', 'room_project'], $this->roomIds($rooms->visibleForUser($ana->userId)));
        self::assertSame(['global', 'room_project'], $this->roomIds($rooms->visibleForUser($bruno->userId)));

        $sessions->disconnect($ana->userId);

        self::assertFalse($sessions->findByUserId($ana->userId)?->connected);
        self::assertCount(2, $sessions->connected());
    }

    /**
     * @param list<Room> $rooms
     *
     * @return list<string>
     */
    private function roomIds(array $rooms): array
    {
        return array_map(static fn (Room $room): string => $room->id, $rooms);
    }
}
