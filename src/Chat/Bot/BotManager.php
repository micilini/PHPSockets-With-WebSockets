<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat\Bot;

use Micilini\PhpSockets\Contracts\BotInterface;

final class BotManager
{
    /**
     * @var list<BotInterface>
     */
    private array $bots = [];

    public function register(BotInterface $bot): self
    {
        $this->bots[] = $bot;

        return $this;
    }

    /**
     * @return list<BotInterface>
     */
    public function all(): array
    {
        return $this->bots;
    }

    public function hasBots(): bool
    {
        return $this->bots !== [];
    }

    /**
     * @return list<array{bot: BotInterface, response: BotResponse}>
     */
    public function handle(BotContext $context): array
    {
        $responses = [];

        foreach ($this->bots as $bot) {
            $response = $bot->handle($context);

            if (!$response instanceof BotResponse) {
                continue;
            }

            $text = trim($response->text);

            if ($text === '') {
                continue;
            }

            $responses[] = [
                'bot' => $bot,
                'response' => BotResponse::text($text, $response->metadata),
            ];
        }

        return $responses;
    }
}
