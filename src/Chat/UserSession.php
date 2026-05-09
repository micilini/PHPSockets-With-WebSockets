<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use DateTimeImmutable;

final class UserSession
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $userId,
        public readonly string $displayName,
        public readonly string $normalizedDisplayName,
        public bool $connected,
        public readonly DateTimeImmutable $connectedAt,
        public DateTimeImmutable $lastSeenAt,
    ) {
    }

    public static function create(string $displayName, string $normalizedDisplayName): self
    {
        $now = new DateTimeImmutable();

        return new self(
            sessionId: 'sess_' . bin2hex(random_bytes(16)),
            userId: 'usr_' . bin2hex(random_bytes(16)),
            displayName: $displayName,
            normalizedDisplayName: $normalizedDisplayName,
            connected: true,
            connectedAt: $now,
            lastSeenAt: $now,
        );
    }

    public function disconnect(): void
    {
        $this->connected = false;
        $this->lastSeenAt = new DateTimeImmutable();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sessionId' => $this->sessionId,
            'userId' => $this->userId,
            'displayName' => $this->displayName,
            'connected' => $this->connected,
            'connectedAt' => $this->connectedAt->format(DATE_ATOM),
            'lastSeenAt' => $this->lastSeenAt->format(DATE_ATOM),
        ];
    }
}
