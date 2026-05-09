<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Protocol;

enum Opcode: int
{
    case CONTINUATION = 0x0;
    case TEXT = 0x1;
    case BINARY = 0x2;
    case CLOSE = 0x8;
    case PING = 0x9;
    case PONG = 0xA;

    public function isControl(): bool
    {
        return in_array($this, [self::CLOSE, self::PING, self::PONG], true);
    }
}
