<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/bots/EchoBot.php';
require __DIR__ . '/bots/HelpBot.php';

use Micilini\PhpSockets\Chat\ChatMessage;
use Micilini\PhpSockets\Chat\ChatServer;
use Micilini\PhpSockets\Chat\UserSession;
use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\Connection\Connection;
use Micilini\PhpSockets\Examples\PrivateChat\Bots\EchoBot;
use Micilini\PhpSockets\Examples\PrivateChat\Bots\HelpBot;

$host = getenv('PHPSOCKETS_HOST') ?: '127.0.0.1';
$port = (int) (getenv('PHPSOCKETS_PORT') ?: 8080);

echo "PHPSockets PrivateChat server running on ws://{$host}:{$port}\n";
echo "Open the browser UI with: php -S 127.0.0.1:8002 -t examples/private-chat/public\n";
echo "Press Ctrl+C to stop the WebSocket server.\n\n";

$server = ChatServer::create(
    ServerConfig::new(host: $host, port: $port, maxPayloadBytes: 4 * 1024 * 1024),
    ChatConfig::new(),
);

$server->bots()
    ->register(new HelpBot())
    ->register(new EchoBot());

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

    echo "[private.user.joined] {$session->displayName} joined. Online users: {$onlineCount}\n";
});

$server->on('user.left', function (array $event): void {
    $session = $event['session'] ?? null;
    $userId = (string) ($event['userId'] ?? 'unknown');

    if ($session instanceof UserSession) {
        echo "[private.user.left] {$session->displayName} left.\n";
        return;
    }

    echo "[private.user.left] {$userId} left.\n";
});

$server->on('message.received', function (array $event): void {
    $message = $event['message'] ?? null;
    $scope = (string) ($event['scope'] ?? 'unknown');

    if (!$message instanceof ChatMessage) {
        return;
    }

    $body = is_string($message->body) ? $message->body : '[file attachment]';

    echo "[private.message.received] scope={$scope} room={$message->roomId} from={$message->fromUserId}: {$body}\n";
});

$server->on('bot.responded', function (array $event): void {
    $message = $event['message'] ?? null;
    $scope = (string) ($event['scope'] ?? 'unknown');

    if (!$message instanceof ChatMessage) {
        return;
    }

    echo "[private.bot.responded] scope={$scope} room={$message->roomId} from={$message->fromUserId}\n";
});

$server->run();
