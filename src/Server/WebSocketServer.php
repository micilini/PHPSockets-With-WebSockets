<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Server;

use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\Contracts\ConnectionRegistryInterface;
use Micilini\PhpSockets\Contracts\EventDispatcherInterface;
use Micilini\PhpSockets\Events\CallbackEventDispatcher;
use Micilini\PhpSockets\Events\ConnectionClosed;
use Micilini\PhpSockets\Events\ConnectionOpened;
use Micilini\PhpSockets\Events\Event;
use Micilini\PhpSockets\Events\MessageReceived;
use Micilini\PhpSockets\Events\MessageSent;
use Micilini\PhpSockets\Events\ServerError;
use Micilini\PhpSockets\Protocol\Frame;

final class WebSocketServer
{
    private readonly EventDispatcherInterface $dispatcher;
    private readonly ServerRuntime $runtime;

    public function __construct(ServerConfig $config, ?EventDispatcherInterface $dispatcher = null, ?ServerRuntime $runtime = null)
    {
        $this->dispatcher = $dispatcher ?? new CallbackEventDispatcher();
        $this->runtime = $runtime ?? new ServerRuntime($config, $this->dispatcher);
    }

    public function on(string $eventName, callable $listener): self
    {
        $this->dispatcher->listen($eventName, function (Event $event) use ($listener): void {
            match (true) {
                $event instanceof ConnectionOpened => $listener($event->connection),
                $event instanceof MessageReceived => $listener($event->connection, $event->frame),
                $event instanceof MessageSent => $listener($event->connection, $event->frame),
                $event instanceof ConnectionClosed => $listener($event->connection, $event->code, $event->reason),
                $event instanceof ServerError => $listener($event->exception, $event->connection),
                default => $listener($event),
            };
        });

        return $this;
    }

    public function run(): void
    {
        $this->runtime->run();
    }

    public function stop(): void
    {
        $this->runtime->stop();
    }

    public function broadcast(string|Frame $message): int
    {
        return $this->runtime->broadcast($message);
    }

    public function connections(): ConnectionRegistryInterface
    {
        return $this->runtime->connections();
    }

    public function connectionCount(): int
    {
        return $this->connections()->count();
    }

    public function dispatcher(): EventDispatcherInterface
    {
        return $this->dispatcher;
    }
}
