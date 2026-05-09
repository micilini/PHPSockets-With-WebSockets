<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Events;

use Micilini\PhpSockets\Connection\Connection;

final class ConnectionClosed extends Event
{
    public function __construct(
        public readonly Connection $connection,
        public readonly int $code = 1000,
        public readonly string $reason = '',
    ) {
    }

    public function name(): string
    {
        return 'close';
    }
}
