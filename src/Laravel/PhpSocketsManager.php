<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Laravel;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Micilini\PhpSockets\Chat\ChatServer;
use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\Storage\Pdo\PdoConnectionFactory;
use PDO;
use RuntimeException;

final readonly class PhpSocketsManager
{
    public function __construct(private ConfigRepository $config)
    {
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public function serverConfig(array $overrides = []): ServerConfig
    {
        $server = $this->config->get('phpsockets.server', []);

        if (!is_array($server)) {
            $server = [];
        }

        $server = array_merge($server, $overrides);

        return ServerConfig::new(
            host: (string) ($server['host'] ?? '127.0.0.1'),
            port: (int) ($server['port'] ?? 8080),
            maxPayloadBytes: (int) ($server['max_payload_bytes'] ?? 4 * 1024 * 1024),
            tickMicroseconds: (int) ($server['tick_microseconds'] ?? 10000),
            connectionLimit: (int) ($server['connection_limit'] ?? 100),
            enableDebugLogs: (bool) ($server['debug'] ?? false),
        );
    }

    public function chatConfig(): ChatConfig
    {
        $chat = $this->config->get('phpsockets.chat', []);

        if (!is_array($chat)) {
            $chat = [];
        }

        $allowedMimeTypes = $chat['allowed_attachment_mime_types'] ?? null;

        return ChatConfig::new(
            maxDisplayNameLength: (int) ($chat['max_display_name_length'] ?? 40),
            maxRoomNameLength: (int) ($chat['max_room_name_length'] ?? 80),
            maxPrivateGroupMembers: (int) ($chat['max_private_group_members'] ?? 20),
            allowGuestSessions: (bool) ($chat['allow_guest_sessions'] ?? true),
            historyLimit: (int) ($chat['history_limit'] ?? 50),
            maxAttachmentBytes: (int) ($chat['max_attachment_bytes'] ?? 2 * 1024 * 1024),
            maxAttachmentFileNameLength: (int) ($chat['max_attachment_file_name_length'] ?? 180),
            allowedAttachmentMimeTypes: is_array($allowedMimeTypes) ? array_values($allowedMimeTypes) : null,
        );
    }

    /**
     * @param array<string, mixed> $serverOverrides
     */
    public function server(array $serverOverrides = []): ChatServer
    {
        return ChatServer::create(
            serverConfig: $this->serverConfig($serverOverrides),
            chatConfig: $this->chatConfig(),
        );
    }

    public function storageDriver(?string $override = null): string
    {
        $driver = $override ?? $this->config->get('phpsockets.storage.driver', 'memory');

        return strtolower(trim((string) $driver));
    }

    public function pdo(?string $driver = null, ?string $databaseOverride = null): PDO
    {
        $driver = $this->storageDriver($driver);

        if ($driver === 'sqlite') {
            $database = $databaseOverride ?: (string) $this->config->get('phpsockets.storage.database');

            if ($database === '') {
                throw new RuntimeException('SQLite database path is required.');
            }

            $directory = dirname($database);

            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException("Unable to create SQLite database directory: {$directory}");
            }

            return PdoConnectionFactory::sqlite($database);
        }

        if (in_array($driver, ['mysql', 'pgsql'], true)) {
            $dsn = (string) $this->config->get('phpsockets.storage.dsn', '');

            if ($dsn === '') {
                throw new RuntimeException("A PDO DSN is required for {$driver} storage.");
            }

            return PdoConnectionFactory::create(
                dsn: $dsn,
                username: $this->nullableString($this->config->get('phpsockets.storage.username')),
                password: $this->nullableString($this->config->get('phpsockets.storage.password')),
            );
        }

        throw new RuntimeException("Storage driver {$driver} does not use PDO.");
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }
}
