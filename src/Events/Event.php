<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Events;

abstract class Event
{
    abstract public function name(): string;
}
