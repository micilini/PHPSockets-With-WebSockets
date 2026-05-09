<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Config;

use InvalidArgumentException;

final readonly class ServerConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public int $maxPayloadBytes,
        public int $tickMicroseconds,
        public int $connectionLimit,
        public bool $enableDebugLogs,
    ) {
        if ($this->host === '') {
            throw new InvalidArgumentException('Server host cannot be empty.');
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw new InvalidArgumentException('Server port must be between 1 and 65535.');
        }

        if ($this->maxPayloadBytes < 1) {
            throw new InvalidArgumentException('Maximum payload size must be greater than zero.');
        }

        if ($this->tickMicroseconds < 1) {
            throw new InvalidArgumentException('Tick interval must be greater than zero.');
        }

        if ($this->connectionLimit < 1) {
            throw new InvalidArgumentException('Connection limit must be greater than zero.');
        }
    }

    public static function new(
        string $host = '127.0.0.1',
        int $port = 8080,
        int $maxPayloadBytes = 65536,
        int $tickMicroseconds = 10000,
        int $connectionLimit = 100,
        bool $enableDebugLogs = false,
    ): self {
        return new self(
            host: $host,
            port: $port,
            maxPayloadBytes: $maxPayloadBytes,
            tickMicroseconds: $tickMicroseconds,
            connectionLimit: $connectionLimit,
            enableDebugLogs: $enableDebugLogs,
        );
    }
}
