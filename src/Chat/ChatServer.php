<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\Server\WebSocketServer;

final readonly class ChatServer
{
    public function __construct(
        private WebSocketServer $server,
        private ChatKernel $kernel,
    ) {
        $this->kernel->attach($this->server);
    }

    public static function create(ServerConfig $serverConfig, ChatConfig $chatConfig): self
    {
        return new self(
            server: new WebSocketServer($serverConfig),
            kernel: new ChatKernel($chatConfig),
        );
    }

    public function on(string $eventName, callable $listener): self
    {
        $this->server->on($eventName, $listener);

        return $this;
    }

    public function run(): void
    {
        $this->server->run();
    }

    public function stop(): void
    {
        $this->server->stop();
    }

    public function webSocketServer(): WebSocketServer
    {
        return $this->server;
    }

    public function kernel(): ChatKernel
    {
        return $this->kernel;
    }
}
