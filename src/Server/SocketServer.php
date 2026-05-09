<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Server;

use Micilini\PhpSockets\Config\ServerConfig;
use RuntimeException;
use Socket;

final class SocketServer
{
    private ?Socket $socket = null;

    public function __construct(private readonly ServerConfig $config)
    {
    }

    public function start(): Socket
    {
        if ($this->socket instanceof Socket) {
            return $this->socket;
        }

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (!$socket instanceof Socket) {
            throw new RuntimeException('Failed to create the server socket.');
        }

        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (!socket_bind($socket, $this->config->host, $this->config->port)) {
            $message = socket_strerror(socket_last_error($socket));
            socket_close($socket);

            throw new RuntimeException("Failed to bind the server socket: {$message}");
        }

        if (!socket_listen($socket, $this->config->connectionLimit)) {
            $message = socket_strerror(socket_last_error($socket));
            socket_close($socket);

            throw new RuntimeException("Failed to listen on the server socket: {$message}");
        }

        socket_set_nonblock($socket);
        $this->socket = $socket;

        return $socket;
    }

    public function socket(): Socket
    {
        if (!$this->socket instanceof Socket) {
            throw new RuntimeException('The server socket has not been started.');
        }

        return $this->socket;
    }

    public function accept(): ?Socket
    {
        $client = socket_accept($this->socket());

        if (!$client instanceof Socket) {
            return null;
        }

        return $client;
    }

    public function close(): void
    {
        if (!$this->socket instanceof Socket) {
            return;
        }

        socket_close($this->socket);
        $this->socket = null;
    }
}
