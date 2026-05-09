<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Storage\InMemory;

use Micilini\PhpSockets\Chat\UserSession;
use Micilini\PhpSockets\Contracts\PresenceStoreInterface;
use Micilini\PhpSockets\Contracts\SessionStoreInterface;

final class InMemorySessionStore implements SessionStoreInterface, PresenceStoreInterface
{
    /**
     * @var array<string, UserSession>
     */
    private array $sessionsById = [];

    /**
     * @var array<string, string>
     */
    private array $sessionIdsByUserId = [];

    public function save(UserSession $session): void
    {
        $this->sessionsById[$session->sessionId] = $session;
        $this->sessionIdsByUserId[$session->userId] = $session->sessionId;
    }

    public function findByUserId(string $userId): ?UserSession
    {
        $sessionId = $this->sessionIdsByUserId[$userId] ?? null;

        if ($sessionId === null) {
            return null;
        }

        return $this->findBySessionId($sessionId);
    }

    public function findBySessionId(string $sessionId): ?UserSession
    {
        return $this->sessionsById[$sessionId] ?? null;
    }

    public function findConnectedByNormalizedDisplayName(string $normalizedDisplayName): ?UserSession
    {
        foreach ($this->sessionsById as $session) {
            if ($session->connected && $session->normalizedDisplayName === $normalizedDisplayName) {
                return $session;
            }
        }

        return null;
    }

    public function connected(): array
    {
        return array_values(array_filter(
            $this->sessionsById,
            static fn (UserSession $session): bool => $session->connected,
        ));
    }

    public function disconnect(string $userId): void
    {
        $session = $this->findByUserId($userId);

        if ($session instanceof UserSession) {
            $session->disconnect();
        }
    }

    public function isOnline(string $userId): bool
    {
        $session = $this->findByUserId($userId);

        return $session instanceof UserSession && $session->connected;
    }

    public function onlineUserIds(): array
    {
        return array_map(
            static fn (UserSession $session): string => $session->userId,
            $this->connected(),
        );
    }
}
