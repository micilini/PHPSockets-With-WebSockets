# MediumChat Example

MediumChat is the intermediate PHPSockets example.

It demonstrates the same global chat flow from EasyChat, but adds:

- High-level chat callbacks.
- Socket lifecycle callbacks.
- Server-side terminal logs.
- Browser-side realtime event log.
- Typing indicators.
- Safe message rendering with `textContent`.
- Plain HTML, CSS and JavaScript.
- Bootstrap through CDN.

## Difference from EasyChat

EasyChat is focused on the simplest possible global chat.

MediumChat is focused on customization.

The server registers callbacks like:

```php
$server->on('user.joined', function (array $event): void {
    // User joined the chat.
});

$server->on('message.received', function (array $event): void {
    // Message was received by the chat core.
});
```

It also supports low-level socket events:

```php
$server->on('open', function ($connection): void {
    // Raw WebSocket connection opened.
});

$server->on('close', function ($connection, int $code, string $reason): void {
    // Raw WebSocket connection closed.
});
```

## Requirements

From the project root, install dependencies first:

```bash
composer install
```

The PHP `sockets` extension must be enabled.

## Running the WebSocket server

From the project root:

```bash
php examples/medium-chat/server.php
```

By default, the WebSocket server runs at:

```txt
ws://127.0.0.1:8080
```

## Running the browser UI

Open a second terminal and run:

```bash
php -S 127.0.0.1:8001 -t examples/medium-chat/public
```

Then open:

```txt
http://127.0.0.1:8001
```

## Manual test

Open two browser tabs:

```txt
Tab 1: William
Tab 2: Ana
```

Expected behavior:

- Both users should enter the chat.
- Both users should appear in the online users list.
- Messages sent by one tab should appear in the other tab.
- Typing indicators should work.
- Duplicate display names should be rejected.
- Browser events should appear in the right-side panel.
- Server callbacks should appear in the terminal running `server.php`.

## Server events demonstrated

```txt
open
close
error
user.joined
user.left
message.received
room.created
```

## Important notes

This example still uses the global room only.

Direct private messages and private group rooms are demonstrated in later phases.
