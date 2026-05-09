<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Protocol;

use Micilini\PhpSockets\Protocol\Frame;
use Micilini\PhpSockets\Protocol\FrameCodec;
use Micilini\PhpSockets\Protocol\Opcode;
use Micilini\PhpSockets\Protocol\ProtocolException;
use PHPUnit\Framework\TestCase;

final class FrameCodecTest extends TestCase
{
    public function testSmallClientTextFrameIsDecoded(): void
    {
        $codec = new FrameCodec();
        $frame = $codec->decode($this->maskedFrame(Opcode::TEXT, 'Hello'));

        self::assertTrue($frame->fin);
        self::assertSame(Opcode::TEXT, $frame->opcode);
        self::assertSame('Hello', $frame->payload);
        self::assertTrue($frame->masked);
    }

    public function testClientTextFrameWithEmojiIsDecoded(): void
    {
        $codec = new FrameCodec();
        $frame = $codec->decode($this->maskedFrame(Opcode::TEXT, 'Hello 🚀'));

        self::assertSame('Hello 🚀', $frame->payload);
    }

    public function testClientTextFrameWithExtendedPayloadLengthIsDecoded(): void
    {
        $payload = str_repeat('A', 126);
        $codec = new FrameCodec();
        $frame = $codec->decode($this->maskedFrame(Opcode::TEXT, $payload));

        self::assertSame($payload, $frame->payload);
    }

    public function testServerTextFrameIsEncodedWithoutMask(): void
    {
        $codec = new FrameCodec();

        self::assertSame("\x81\x05Hello", $codec->encode(Frame::text('Hello')));
    }

    public function testPayloadAboveConfiguredLimitIsRejected(): void
    {
        $codec = new FrameCodec(maxPayloadBytes: 5);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('WebSocket payload exceeds the configured maximum size.');

        $codec->decode($this->maskedFrame(Opcode::TEXT, 'Too big'));
    }

    public function testPingAndPongFramesAreRecognized(): void
    {
        $codec = new FrameCodec();

        self::assertSame(Opcode::PING, $codec->decode($this->maskedFrame(Opcode::PING, 'ping'))->opcode);
        self::assertSame(Opcode::PONG, $codec->decode($this->maskedFrame(Opcode::PONG, 'pong'))->opcode);
    }

    public function testCloseFrameIsRecognized(): void
    {
        $codec = new FrameCodec();
        $frame = $codec->decode($this->maskedFrame(Opcode::CLOSE, ''));

        self::assertSame(Opcode::CLOSE, $frame->opcode);
        self::assertSame('', $frame->payload);
    }

    public function testUnmaskedClientFrameIsRejected(): void
    {
        $codec = new FrameCodec();

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Client WebSocket frames must be masked.');

        $codec->decode("\x81\x05Hello");
    }

    private function maskedFrame(Opcode $opcode, string $payload): string
    {
        $firstByte = chr(0x80 | $opcode->value);
        $payloadLength = strlen($payload);
        $maskingKey = "\x37\xfa\x21\x3d";

        if ($payloadLength <= 125) {
            $header = $firstByte . chr(0x80 | $payloadLength);
        } elseif ($payloadLength <= 65535) {
            $header = $firstByte . chr(0x80 | 126) . pack('n', $payloadLength);
        } else {
            $header = $firstByte . chr(0x80 | 127) . pack('NN', 0, $payloadLength);
        }

        return $header . $maskingKey . $this->maskPayload($payload, $maskingKey);
    }

    private function maskPayload(string $payload, string $maskingKey): string
    {
        $result = '';
        $payloadLength = strlen($payload);

        for ($index = 0; $index < $payloadLength; $index++) {
            $result .= $payload[$index] ^ $maskingKey[$index % 4];
        }

        return $result;
    }
}
