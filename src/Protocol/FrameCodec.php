<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Protocol;

use InvalidArgumentException;

final readonly class FrameCodec
{
    public function __construct(private int $maxPayloadBytes = 65536)
    {
        if ($this->maxPayloadBytes < 1) {
            throw new InvalidArgumentException('Maximum payload size must be greater than zero.');
        }
    }

    public function decode(string $data, bool $fromClient = true): Frame
    {
        if (strlen($data) < 2) {
            throw new ProtocolException('Incomplete WebSocket frame header.');
        }

        $firstByte = ord($data[0]);
        $secondByte = ord($data[1]);
        $fin = ($firstByte & 0x80) === 0x80;
        $reservedBits = $firstByte & 0x70;

        if ($reservedBits !== 0) {
            throw new ProtocolException('Reserved WebSocket frame bits are not supported.');
        }

        $opcode = Opcode::tryFrom($firstByte & 0x0F);

        if (!$opcode instanceof Opcode) {
            throw new ProtocolException('Unsupported WebSocket opcode.');
        }

        $masked = ($secondByte & 0x80) === 0x80;

        if ($fromClient && !$masked) {
            throw new ProtocolException('Client WebSocket frames must be masked.');
        }

        $payloadLength = $secondByte & 0x7F;
        $offset = 2;

        if ($payloadLength === 126) {
            $this->assertAvailableBytes($data, $offset, 2);
            $lengthParts = unpack('nlength', substr($data, $offset, 2));

            if ($lengthParts === false) {
                throw new ProtocolException('Invalid WebSocket payload length.');
            }

            $payloadLength = (int) $lengthParts['length'];
            $offset += 2;
        } elseif ($payloadLength === 127) {
            $this->assertAvailableBytes($data, $offset, 8);
            $parts = unpack('Nhigh/Nlow', substr($data, $offset, 8));

            if ($parts === false) {
                throw new ProtocolException('Invalid WebSocket payload length.');
            }

            if ((int) $parts['high'] !== 0) {
                throw new ProtocolException('WebSocket payload length is too large.');
            }

            $payloadLength = (int) $parts['low'];
            $offset += 8;
        }

        if ($payloadLength > $this->maxPayloadBytes) {
            throw new ProtocolException('WebSocket payload exceeds the configured maximum size.');
        }

        if ($opcode->isControl()) {
            if (!$fin) {
                throw new ProtocolException('Control frames must not be fragmented.');
            }

            if ($payloadLength > 125) {
                throw new ProtocolException('Control frame payload cannot be larger than 125 bytes.');
            }
        }

        $maskingKey = '';

        if ($masked) {
            $this->assertAvailableBytes($data, $offset, 4);
            $maskingKey = substr($data, $offset, 4);
            $offset += 4;
        }

        $this->assertAvailableBytes($data, $offset, $payloadLength);
        $payload = substr($data, $offset, $payloadLength);

        if ($masked) {
            $payload = self::applyMask($payload, $maskingKey);
        }

        return new Frame($fin, $opcode, $payload, $masked);
    }

    public function encode(Frame $frame, bool $mask = false): string
    {
        $payload = $frame->payload;
        $payloadLength = strlen($payload);

        if ($payloadLength > $this->maxPayloadBytes) {
            throw new ProtocolException('WebSocket payload exceeds the configured maximum size.');
        }

        $firstByte = ($frame->fin ? 0x80 : 0x00) | $frame->opcode->value;
        $header = chr($firstByte);
        $maskBit = $mask ? 0x80 : 0x00;

        if ($payloadLength <= 125) {
            $header .= chr($maskBit | $payloadLength);
        } elseif ($payloadLength <= 65535) {
            $header .= chr($maskBit | 126) . pack('n', $payloadLength);
        } else {
            $header .= chr($maskBit | 127) . pack('NN', 0, $payloadLength);
        }

        if (!$mask) {
            return $header . $payload;
        }

        $maskingKey = random_bytes(4);

        return $header . $maskingKey . self::applyMask($payload, $maskingKey);
    }

    private function assertAvailableBytes(string $data, int $offset, int $neededBytes): void
    {
        if (strlen($data) < $offset + $neededBytes) {
            throw new ProtocolException('Incomplete WebSocket frame payload.');
        }
    }

    private static function applyMask(string $payload, string $maskingKey): string
    {
        $result = '';
        $payloadLength = strlen($payload);

        for ($index = 0; $index < $payloadLength; $index++) {
            $result .= $payload[$index] ^ $maskingKey[$index % 4];
        }

        return $result;
    }
}
