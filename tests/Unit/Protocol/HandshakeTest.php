<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Protocol;

use Micilini\PhpSockets\Protocol\Handshake;
use Micilini\PhpSockets\Protocol\ProtocolException;
use PHPUnit\Framework\TestCase;

final class HandshakeTest extends TestCase
{
    public function testAcceptKeyIsGeneratedFromClientKey(): void
    {
        self::assertSame(
            's3pPLMBiTxaQ9kYGzzhZRbK+xOo=',
            Handshake::acceptKey('dGhlIHNhbXBsZSBub25jZQ==')
        );
    }

    public function testValidRequestGeneratesSwitchingProtocolsResponse(): void
    {
        $response = Handshake::response($this->validRequest());

        self::assertStringContainsString('HTTP/1.1 101 Switching Protocols', $response);
        self::assertStringContainsString('Upgrade: websocket', $response);
        self::assertStringContainsString('Connection: Upgrade', $response);
        self::assertStringContainsString('Sec-WebSocket-Accept: s3pPLMBiTxaQ9kYGzzhZRbK+xOo=', $response);
        self::assertStringEndsWith("\r\n\r\n", $response);
    }

    public function testRequestWithoutValidClientKeyIsRejected(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Invalid WebSocket client key.');

        Handshake::response(str_replace('Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==', '', $this->validRequest()));
    }

    private function validRequest(): string
    {
        return "GET /chat HTTP/1.1\r\n"
            . "Host: example.com:8000\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\n"
            . "Sec-WebSocket-Version: 13\r\n\r\n";
    }
}
