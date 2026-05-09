<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Connection;

enum ConnectionState: string
{
    case CONNECTING = 'connecting';
    case OPEN = 'open';
    case CLOSING = 'closing';
    case CLOSED = 'closed';
}
