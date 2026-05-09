# EasyChat Example

EasyChat is the first modern browser example for PHPSockets With WebSockets.

It demonstrates a simple global chat using:

- Native PHP WebSocket server.
- Composer autoload.
- PHP sockets.
- Chat core with unique display names.
- Plain HTML, CSS and JavaScript.
- Bootstrap through CDN.
- Safe message rendering with `textContent`.

## Requirements

From the project root, install dependencies first:

```bash
composer install
```

The PHP `sockets` extension must be enabled.

## Running the WebSocket server

From the project root:

```bash
php examples/easy-chat/server.php
```

By default, the WebSocket server runs at:

```txt
ws://127.0.0.1:8080
```

You can customize host and port with environment variables:

```bash
PHPSOCKETS_HOST=127.0.0.1 PHPSOCKETS_PORT=8080 php examples/easy-chat/server.php
```

On Windows PowerShell:

```powershell
$env:PHPSOCKETS_HOST="127.0.0.1"
$env:PHPSOCKETS_PORT="8080"
php examples/easy-chat/server.php
```

## Running the browser UI

Open a second terminal and run:

```bash
php -S 127.0.0.1:8000 -t examples/easy-chat/public
```

Then open:

```txt
http://127.0.0.1:8000
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
- Own messages should show a message status icon.
- When the server echoes the message, the status should move from sent to received.
- When another browser receives the message, the sender should see the message as read.
- Duplicate display names should be rejected.
- User messages must be rendered safely without `innerHTML`.

## Important notes

This example is intentionally simple.

Message receipts are browser-only example receipts. They are not persisted and do not represent a full per-user room read history.

It only demonstrates the global chat flow. Private direct messages and private group rooms will be demonstrated in later examples.

## Composer actions

The message input includes a left-side action button.

It opens:

- Emoji picker.
- File picker.

Allowed files:

```txt
image/png
image/jpeg
image/gif
application/pdf
text/plain
```

Default max size:

```txt
2 MB
```

All user-provided text continues to be rendered safely.

## Attachment composer behavior

Selecting a file does not send it immediately.

The selected file appears as a pending attachment in the composer. The user can add a text caption and click `Send`.

Supported files:

```txt
image/png
image/jpeg
image/gif
application/pdf
text/plain
```

Default max file size:

```txt
2 MB
```

Each delivered file message includes a download button.

Attachments are transported as JSON text-frame envelopes with base64 content. The chat core does not accept binary WebSocket frames for chat messages in this version.
