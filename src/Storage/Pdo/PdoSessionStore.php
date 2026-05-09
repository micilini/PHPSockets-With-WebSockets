<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Storage\Pdo;

use DateTimeImmutable;
use Micilini\PhpSockets\Chat\UserSession;
use Micilini\PhpSockets\Contracts\SessionStoreInterface;
use Micilini\PhpSockets\Exceptions\StorageException;
use PDO;
use PDOStatement;

final readonly class PdoSessionStore implements SessionStoreInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(UserSession $session): void
    {
        $this->saveUser($session);
        $this->saveSession($session);
    }

    public function findByUserId(string $userId): ?UserSession
    {
        return $this->findOne(
            'SELECT s.id AS session_id, s.user_id, u.display_name, u.normalized_display_name, s.connected,
                    s.connected_at, s.last_seen_at
             FROM sessions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.user_id = :user_id
             ORDER BY s.connected_at DESC
             LIMIT 1',
            ['user_id' => $userId],
        );
    }

    public function findBySessionId(string $sessionId): ?UserSession
    {
        return $this->findOne(
            'SELECT s.id AS session_id, s.user_id, u.display_name, u.normalized_display_name, s.connected,
                    s.connected_at, s.last_seen_at
             FROM sessions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.id = :session_id
             LIMIT 1',
            ['session_id' => $sessionId],
        );
    }

    public function findConnectedByNormalizedDisplayName(string $normalizedDisplayName): ?UserSession
    {
        return $this->findOne(
            'SELECT s.id AS session_id, s.user_id, u.display_name, u.normalized_display_name, s.connected,
                    s.connected_at, s.last_seen_at
             FROM sessions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE u.normalized_display_name = :normalized_display_name
               AND s.connected = 1
             ORDER BY s.connected_at DESC
             LIMIT 1',
            ['normalized_display_name' => $normalizedDisplayName],
        );
    }

    public function connected(): array
    {
        $statement = $this->prepare(
            'SELECT s.id AS session_id, s.user_id, u.display_name, u.normalized_display_name, s.connected,
                    s.connected_at, s.last_seen_at
             FROM sessions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.connected = 1
             ORDER BY u.display_name ASC',
        );
        $statement->execute();
        $rows = $statement->fetchAll();

        return array_values(array_map(fn (array $row): UserSession => $this->hydrate($row), $rows));
    }

    public function disconnect(string $userId): void
    {
        $statement = $this->prepare(
            'UPDATE sessions
             SET connected = 0, last_seen_at = :last_seen_at
             WHERE user_id = :user_id',
        );
        $statement->execute([
            'last_seen_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'user_id' => $userId,
        ]);
    }

    private function saveUser(UserSession $session): void
    {
        $exists = $this->exists('SELECT 1 FROM users WHERE id = :id LIMIT 1', ['id' => $session->userId]);

        if ($exists) {
            $statement = $this->prepare(
                'UPDATE users
                 SET display_name = :display_name, normalized_display_name = :normalized_display_name
                 WHERE id = :id',
            );
        } else {
            $statement = $this->prepare(
                'INSERT INTO users (id, display_name, normalized_display_name, created_at)
                 VALUES (:id, :display_name, :normalized_display_name, :created_at)',
            );
            $statement->bindValue(':created_at', $session->connectedAt->format(DATE_ATOM));
        }

        $statement->bindValue(':id', $session->userId);
        $statement->bindValue(':display_name', $session->displayName);
        $statement->bindValue(':normalized_display_name', $session->normalizedDisplayName);
        $statement->execute();
    }

    private function saveSession(UserSession $session): void
    {
        $exists = $this->exists('SELECT 1 FROM sessions WHERE id = :id LIMIT 1', ['id' => $session->sessionId]);

        if ($exists) {
            $statement = $this->prepare(
                'UPDATE sessions
                 SET user_id = :user_id, connected = :connected, connected_at = :connected_at, last_seen_at = :last_seen_at
                 WHERE id = :id',
            );
        } else {
            $statement = $this->prepare(
                'INSERT INTO sessions (id, user_id, connected, connected_at, last_seen_at)
                 VALUES (:id, :user_id, :connected, :connected_at, :last_seen_at)',
            );
        }

        $statement->bindValue(':id', $session->sessionId);
        $statement->bindValue(':user_id', $session->userId);
        $statement->bindValue(':connected', $session->connected ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':connected_at', $session->connectedAt->format(DATE_ATOM));
        $statement->bindValue(':last_seen_at', $session->lastSeenAt->format(DATE_ATOM));
        $statement->execute();
    }

    /**
     * @param array<string, string> $parameters
     */
    private function findOne(string $sql, array $parameters): ?UserSession
    {
        $statement = $this->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function exists(string $sql, array $parameters): bool
    {
        $statement = $this->prepare($sql);
        $statement->execute($parameters);

        return is_array($statement->fetch());
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): UserSession
    {
        return new UserSession(
            sessionId: (string) $row['session_id'],
            userId: (string) $row['user_id'],
            displayName: (string) $row['display_name'],
            normalizedDisplayName: (string) $row['normalized_display_name'],
            connected: $this->boolValue($row['connected'] ?? false),
            connectedAt: new DateTimeImmutable((string) $row['connected_at']),
            lastSeenAt: new DateTimeImmutable((string) $row['last_seen_at']),
        );
    }

    private function boolValue(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);

        if (!$statement instanceof PDOStatement) {
            throw new StorageException('Failed to prepare SQL statement.');
        }

        return $statement;
    }
}
