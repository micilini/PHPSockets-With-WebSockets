<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Events;

use Micilini\PhpSockets\Contracts\EventDispatcherInterface;

class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, list<callable(Event): void>>
     */
    private array $listeners = [];

    public function listen(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName] ??= [];
        $this->listeners[$eventName][] = $listener;
    }

    public function dispatch(Event $event): void
    {
        foreach ($this->listeners[$event->name()] ?? [] as $listener) {
            $listener($event);
        }
    }
}
