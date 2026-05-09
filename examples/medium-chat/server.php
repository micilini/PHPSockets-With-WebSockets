<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Micilini\PhpSockets\Chat\ChatMessage;
use Micilini\PhpSockets\Chat\ChatServer;
use Micilini\PhpSockets\Chat\Room;
use Micilini\PhpSockets\Chat\UserSession;
use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\Connection\Connection;

$host = getenv('PHPSOCKETS_HOST') ?: '127.0.0.1';
$port = (int) (getenv('PHPSOCKETS_PORT') ?: 8080);

echo "PHPSockets MediumChat server running on ws://{$host}:{$port}\n";
echo "Open the browser UI with: php -S 127.0.0.1:8001 -t examples/medium-chat/public\n";
echo "Press Ctrl+C to stop the WebSocket server.\n\n";

$server = ChatServer::create(
    ServerConfig::new(host: $host, port: $port),
    ChatConfig::new(),
);

$server->on('open', function (Connection $connection): void {
    echo "[socket.open] {$connection->id()} connected from {$connection->remoteAddress()}\n";
});

$server->on('close', function (Connection $connection, int $code, string $reason): void {
    echo "[socket.close] {$connection->id()} closed with code {$code}";

    if ($reason !== '') {
        echo " and reason {$reason}";
    }

    echo "\n";
});

$server->on('error', function (Throwable $exception, ?Connection $connection): void {
    $connectionId = $connection instanceof Connection ? $connection->id() : 'server';

    echo "[socket.error] {$connectionId}: {$exception->getMessage()}\n";
});

$server->on('user.joined', function (array $event): void {
    $session = $event['session'] ?? null;

    if (!$session instanceof UserSession) {
        return;
    }

    $onlineCount = $event['onlineCount'] ?? 0;

    echo "[chat.user.joined] {$session->displayName} joined. Online users: {$onlineCount}\n";
});

$server->on('user.left', function (array $event): void {
    $session = $event['session'] ?? null;
    $userId = (string) ($event['userId'] ?? 'unknown');

    if ($session instanceof UserSession) {
        echo "[chat.user.left] {$session->displayName} left.\n";
        return;
    }

    echo "[chat.user.left] {$userId} left.\n";
});

$server->on('message.received', function (array $event): void {
    $message = $event['message'] ?? null;
    $scope = (string) ($event['scope'] ?? 'unknown');

    if (!$message instanceof ChatMessage) {
        return;
    }

    echo "[chat.message.received] scope={$scope} room={$message->roomId} from={$message->fromUserId}: {$message->body}\n";
});

$server->on('room.created', function (array $event): void {
    $room = $event['room'] ?? null;

    if (!$room instanceof Room) {
        return;
    }

    echo "[chat.room.created] {$room->id} type={$room->type} members=" . count($room->memberUserIds) . "\n";
});

$server->run();
