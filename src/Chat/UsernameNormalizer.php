<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use Micilini\PhpSockets\Exceptions\InvalidPayloadException;

final readonly class UsernameNormalizer
{
    public function __construct(private int $maxLength = 40)
    {
    }

    public function displayName(string $displayName): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($displayName));

        if (!is_string($normalized) || $normalized === '') {
            throw new InvalidPayloadException('Display name cannot be empty.');
        }

        if (strlen($normalized) > $this->maxLength) {
            throw new InvalidPayloadException('Display name is too long.');
        }

        return $normalized;
    }

    public function key(string $displayName): string
    {
        return strtolower($this->displayName($displayName));
    }
}
