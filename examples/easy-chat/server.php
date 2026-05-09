<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Micilini\PhpSockets\Chat\ChatServer;
use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Config\ServerConfig;

$host = getenv('PHPSOCKETS_HOST') ?: '127.0.0.1';
$port = (int) (getenv('PHPSOCKETS_PORT') ?: 8080);

echo "PHPSockets EasyChat server running on ws://{$host}:{$port}\n";
echo "Open the browser UI with: php -S 127.0.0.1:8000 -t examples/easy-chat/public\n";
echo "Press Ctrl+C to stop the WebSocket server.\n\n";

$server = ChatServer::create(
    ServerConfig::new(host: $host, port: $port),
    ChatConfig::new(),
);

$server->run();