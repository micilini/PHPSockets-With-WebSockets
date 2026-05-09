<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Connection;

use Micilini\PhpSockets\Protocol\CloseCode;
use Micilini\PhpSockets\Protocol\Frame;
use Micilini\PhpSockets\Protocol\FrameCodec;
use RuntimeException;
use Socket;

final class Connection
{
    private ConnectionState $state;
    private ?string $userId = null;

    public function __construct(
        private readonly string $id,
        private readonly Socket $socket,
        private readonly FrameCodec $codec,
        private readonly ?string $remoteAddress = null,
        ConnectionState $state = ConnectionState::OPEN,
    ) {
        $this->state = $state;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function socket(): Socket
    {
        return $this->socket;
    }

    public function socketId(): int
    {
        return spl_object_id($this->socket);
    }

    public function state(): ConnectionState
    {
        return $this->state;
    }

    public function remoteAddress(): ?string
    {
        return $this->remoteAddress;
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function markOpen(): void
    {
        $this->state = ConnectionState::OPEN;
    }

    public function markClosing(): void
    {
        $this->state = ConnectionState::CLOSING;
    }

    public function markClosed(): void
    {
        $this->state = ConnectionState::CLOSED;
    }

    public function send(string|Frame $message): void
    {
        if ($this->state === ConnectionState::CLOSED) {
            throw new RuntimeException('Cannot send data to a closed WebSocket connection.');
        }

        $frame = is_string($message) ? Frame::text($message) : $message;
        $this->writeAll($this->codec->encode($frame));
    }

    public function close(int $code = CloseCode::NORMAL_CLOSURE->value, string $reason = ''): void
    {
        if ($this->state === ConnectionState::CLOSED) {
            return;
        }

        $this->state = ConnectionState::CLOSING;
        $payload = pack('n', $code) . $reason;

        try {
            $this->send(Frame::close($payload));
        } catch (RuntimeException) {
        }

        socket_close($this->socket);
        $this->state = ConnectionState::CLOSED;
    }

    private function writeAll(string $bytes): void
    {
        $length = strlen($bytes);
        $written = 0;

        while ($written < $length) {
            $result = socket_write($this->socket, substr($bytes, $written));

            if ($result === false || $result === 0) {
                throw new RuntimeException('Failed to write data to the WebSocket connection.');
            }

            $written += $result;
        }
    }
}
