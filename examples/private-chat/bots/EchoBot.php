<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Examples\PrivateChat\Bots;

use Micilini\PhpSockets\Chat\Bot\BotContext;
use Micilini\PhpSockets\Chat\Bot\BotResponse;
use Micilini\PhpSockets\Contracts\BotInterface;

final class EchoBot implements BotInterface
{
    public function name(): string
    {
        return 'Echo Bot';
    }

    public function handle(BotContext $context): ?BotResponse
    {
        $text = trim($context->text());

        if (!str_starts_with($text, '/echo ')) {
            return null;
        }

        $message = trim(substr($text, 6));

        if ($message === '') {
            return BotResponse::text('Usage: /echo <text>');
        }

        return BotResponse::text($message);
    }
}
