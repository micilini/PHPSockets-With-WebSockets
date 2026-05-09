<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Protocol;

final readonly class Frame
{
    public function __construct(
        public bool $fin,
        public Opcode $opcode,
        public string $payload = '',
        public bool $masked = false,
    ) {
        $payloadLength = strlen($this->payload);

        if ($this->opcode->isControl() && !$this->fin) {
            throw new ProtocolException('Control frames must not be fragmented.');
        }

        if ($this->opcode->isControl() && $payloadLength > 125) {
            throw new ProtocolException('Control frame payload cannot be larger than 125 bytes.');
        }
    }

    public static function text(string $payload): self
    {
        return new self(true, Opcode::TEXT, $payload);
    }

    public static function binary(string $payload): self
    {
        return new self(true, Opcode::BINARY, $payload);
    }

    public static function close(string $payload = ''): self
    {
        return new self(true, Opcode::CLOSE, $payload);
    }

    public static function ping(string $payload = ''): self
    {
        return new self(true, Opcode::PING, $payload);
    }

    public static function pong(string $payload = ''): self
    {
        return new self(true, Opcode::PONG, $payload);
    }
}
