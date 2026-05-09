<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Events;

use Micilini\PhpSockets\Connection\Connection;

final class ConnectionOpened extends Event
{
    public function __construct(public readonly Connection $connection)
    {
    }

    public function name(): string
    {
        return 'open';
    }
}
