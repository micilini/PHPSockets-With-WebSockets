<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Events;

use Micilini\PhpSockets\Connection\Connection;
use Micilini\PhpSockets\Protocol\Frame;

final class MessageSent extends Event
{
    public function __construct(
        public readonly Connection $connection,
        public readonly Frame $frame,
    ) {
    }

    public function name(): string
    {
        return 'sent';
    }
}
