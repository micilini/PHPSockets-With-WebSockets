<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Examples\PrivateChat\Bots;

use Micilini\PhpSockets\Chat\Bot\BotContext;
use Micilini\PhpSockets\Chat\Bot\BotResponse;
use Micilini\PhpSockets\Contracts\BotInterface;

final class HelpBot implements BotInterface
{
    public function name(): string
    {
        return 'Help Bot';
    }

    public function handle(BotContext $context): ?BotResponse
    {
        if (trim($context->text()) !== '/help') {
            return null;
        }

        return BotResponse::text('Available commands: /help, /echo <text>');
    }
}
