<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Events;

use Micilini\PhpSockets\Connection\Connection;
use Throwable;

final class ServerError extends Event
{
    public function __construct(
        public readonly Throwable $exception,
        public readonly ?Connection $connection = null,
    ) {
    }

    public function name(): string
    {
        return 'error';
    }
}
