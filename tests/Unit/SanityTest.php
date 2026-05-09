<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit;

use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\WebSocket;
use PHPUnit\Framework\TestCase;

final class SanityTest extends TestCase
{
    public function testVersionIsAvailable(): void
    {
        self::assertSame('0.1.0-dev', WebSocket::version());
    }

    public function testServerConfigDefaultsAreAvailable(): void
    {
        $config = ServerConfig::new();

        self::assertSame('127.0.0.1', $config->host);
        self::assertSame(8080, $config->port);
        self::assertSame(65536, $config->maxPayloadBytes);
        self::assertSame(10000, $config->tickMicroseconds);
        self::assertSame(100, $config->connectionLimit);
        self::assertFalse($config->enableDebugLogs);
    }

    public function testChatConfigDefaultsAreAvailable(): void
    {
        $config = ChatConfig::new();

        self::assertSame(40, $config->maxDisplayNameLength);
        self::assertSame(80, $config->maxRoomNameLength);
        self::assertSame(20, $config->maxPrivateGroupMembers);
        self::assertTrue($config->allowGuestSessions);
        self::assertSame(50, $config->historyLimit);
    }
}
