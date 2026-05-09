<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Server;

final class Loop
{
    private bool $running = false;

    public function start(): void
    {
        $this->running = true;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }
}
