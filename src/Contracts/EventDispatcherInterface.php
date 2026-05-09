<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Contracts;

use Micilini\PhpSockets\Events\Event;

interface EventDispatcherInterface
{
    public function listen(string $eventName, callable $listener): void;

    public function dispatch(Event $event): void;
}
