<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat\Bot;

final readonly class BotResponse
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $text,
        public array $metadata = [],
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function text(string $text, array $metadata = []): self
    {
        return new self($text, $metadata);
    }
}
