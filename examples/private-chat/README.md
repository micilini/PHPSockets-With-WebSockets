# PrivateChat Example

PrivateChat is the direct messaging example for PHPSockets With WebSockets.

It demonstrates:

- Unique display names.
- Online users list.
- Global room.
- Private direct 1:1 conversations.
- Private group rooms with selected online users.
- Direct messages delivered only to the sender and the selected recipient.
- Group messages delivered only to selected members.
- Unread badges for global, direct and private group conversations.
- Typing indicators for global, direct and private group conversations.
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

## Private group rooms

PrivateChat also supports private group rooms.

A user can click `+ New private room`, select online users, optionally name the room, and create a private conversation.

Only selected members receive the room and its messages.

## Manual group test

Open four browser tabs:

```txt
Tab 1: William
Tab 2: Ana
Tab 3: Bruno
Tab 4: Carla
```

Expected behavior:

- William creates a room with Ana and Bruno.
- William, Ana and Bruno see the new room.
- Carla does not see the room.
- Messages in that room are delivered only to William, Ana and Bruno.
- Carla receives no group messages.
- Unread badges appear when a message arrives in a room that is not currently open.

## Unread badges

PrivateChat displays unread badges for Global Room, direct conversations and private group rooms.

Badges increase while a conversation is not open and reset when the conversation is opened.

## Storage note

This example still uses in-memory storage by default.

The package now includes optional storage adapters and migrations, but the official CLI/config workflow is added in a later phase.

## Important notes

This phase implements direct 1:1 private messaging and private group rooms.
