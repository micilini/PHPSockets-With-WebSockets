<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Contracts;

use Micilini\PhpSockets\Chat\UserSession;

interface SessionStoreInterface
{
    public function save(UserSession $session): void;

    public function findByUserId(string $userId): ?UserSession;

    public function findBySessionId(string $sessionId): ?UserSession;

    public function findConnectedByNormalizedDisplayName(string $normalizedDisplayName): ?UserSession;

    /**
     * @return list<UserSession>
     */
    public function connected(): array;

    public function disconnect(string $userId): void;
}
