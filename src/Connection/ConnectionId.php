<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Connection;

final class ConnectionId
{
    public static function generate(): string
    {
        return 'conn_' . bin2hex(random_bytes(16));
    }
}
