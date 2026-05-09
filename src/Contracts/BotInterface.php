<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Contracts;

use Micilini\PhpSockets\Chat\Bot\BotContext;
use Micilini\PhpSockets\Chat\Bot\BotResponse;

interface BotInterface
{
    public function name(): string;

    public function handle(BotContext $context): ?BotResponse;
}
