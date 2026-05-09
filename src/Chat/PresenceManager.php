<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use Micilini\PhpSockets\Contracts\SessionStoreInterface;
use Micilini\PhpSockets\Exceptions\UsernameAlreadyTakenException;

final readonly class PresenceManager
{
    public function __construct(
        private UsernameNormalizer $normalizer,
        private SessionStoreInterface $sessions,
    ) {
    }

    public function join(string $displayName): UserSession
    {
        $normalizedDisplayName = $this->normalizer->displayName($displayName);
        $normalizedKey = $this->normalizer->key($normalizedDisplayName);

        if ($this->sessions->findConnectedByNormalizedDisplayName($normalizedKey) instanceof UserSession) {
            throw new UsernameAlreadyTakenException('This display name is already in use.');
        }

        $session = UserSession::create($normalizedDisplayName, $normalizedKey);

        $this->sessions->save($session);

        return $session;
    }

    public function leave(string $userId): void
    {
        $this->sessions->disconnect($userId);
    }

    /**
     * @return list<UserSession>
     */
    public function connectedSessions(): array
    {
        return $this->sessions->connected();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function snapshot(): array
    {
        return array_map(
            static fn (UserSession $session): array => $session->toArray(),
            $this->connectedSessions(),
        );
    }
}
