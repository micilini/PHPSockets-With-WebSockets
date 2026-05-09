<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Storage;

use Micilini\PhpSockets\Chat\ChatMessage;
use Micilini\PhpSockets\Chat\Room;
use Micilini\PhpSockets\Chat\UserSession;
use Micilini\PhpSockets\Storage\InMemory\InMemoryMessageStore;
use Micilini\PhpSockets\Storage\InMemory\InMemoryRoomStore;
use Micilini\PhpSockets\Storage\InMemory\InMemorySessionStore;
use PHPUnit\Framework\TestCase;

final class InMemoryStoreTest extends TestCase
{
    public function testSessionStoreSavesFindsListsAndDisconnectsSessions(): void
    {
        $store = new InMemorySessionStore();
        $william = UserSession::create('William', 'william');
        $ana = UserSession::create('Ana', 'ana');

        $store->save($william);
        $store->save($ana);

        self::assertSame($william, $store->findByUserId($william->userId));
        self::assertSame($ana, $store->findBySessionId($ana->sessionId));
        self::assertSame($william, $store->findConnectedByNormalizedDisplayName('william'));
        self::assertSame([$william, $ana], $store->connected());

        $store->disconnect($william->userId);

        self::assertFalse($william->connected);
        self::assertNull($store->findConnectedByNormalizedDisplayName('william'));
        self::assertSame([$ana], $store->connected());
    }

    public function testRoomStoreSavesVisibilityAndMembershipChanges(): void
    {
        $store = new InMemoryRoomStore();
        $global = Room::global();
        $direct = Room::direct('direct_william_ana', ['usr_william', 'usr_ana'], 'usr_william');
        $group = Room::privateGroup('Project', 'usr_william', ['usr_william', 'usr_ana', 'usr_bruno']);

        $store->save($global);
        $store->save($direct);
        $store->save($group);

        self::assertSame($direct, $store->find('direct_william_ana'));
        self::assertSame([$global, $direct, $group], $store->all());
        self::assertSame([$global, $direct, $group], $store->visibleForUser('usr_ana'));
        self::assertSame([$global, $group], $store->visibleForUser('usr_bruno'));

        $store->addMember($direct->id, 'usr_bruno');

        self::assertTrue($store->find($direct->id)?->hasMember('usr_bruno'));

        $store->removeMember($direct->id, 'usr_ana');

        self::assertFalse($store->find($direct->id)?->hasMember('usr_ana'));
    }

    public function testMessageStoreSavesMessagesAndAppliesRoomLimit(): void
    {
        $store = new InMemoryMessageStore();
        $first = ChatMessage::text('global', 'usr_william', 'First');
        $second = ChatMessage::text('global', 'usr_ana', 'Second');
        $third = ChatMessage::text('direct_william_ana', 'usr_william', 'Private');

        $store->save($first);
        $store->save($second);
        $store->save($third);

        self::assertSame([$first, $second], $store->messagesForRoom('global'));
        self::assertSame([$second], $store->messagesForRoom('global', 1));
        self::assertSame([], $store->messagesForRoom('global', 0));
        self::assertSame([$third], $store->messagesForRoom('direct_william_ana'));
    }
}
