# PrivateChat Example

PrivateChat is the direct messaging example for PHPSockets With WebSockets.

It demonstrates:

- Unique display names.
- Online users list.
- Global room.
- Private direct 1:1 conversations.
- Direct messages delivered only to the sender and the selected recipient.
- Typing indicators for global and direct conversations.
- Simple message receipts for sent, received and read states.
- Safe rendering with `textContent`.
- Plain HTML, CSS and JavaScript.
- Bootstrap through CDN.

## Requirements

From the project root:

```bash
composer install
```

The PHP `sockets` extension must be enabled.

## Running the WebSocket server

```bash
php examples/private-chat/server.php
```

By default:

```txt
ws://127.0.0.1:8080
```

## Running the browser UI

Open a second terminal:

```bash
php -S 127.0.0.1:8002 -t examples/private-chat/public
```

Then open:

```txt
http://127.0.0.1:8002
```

## Manual test

Open three browser tabs:

```txt
Tab 1: William
Tab 2: Ana
Tab 3: Bruno
```

Expected behavior:

- All users enter with unique names.
- All users see the online list.
- Global messages appear for everyone.
- William clicks Ana and sends a private message.
- Ana receives the private message.
- Bruno does not receive the private message.
- Ana can reply to William.
- Typing in the private conversation appears only for the selected recipient.
- Message status moves from sent to received/read.
- Duplicate names are rejected.
- User messages are rendered safely without `innerHTML`.

## Important notes

This phase implements direct 1:1 private messaging.

Private group rooms are implemented in the next phase.
