# PHPSockets With WebSockets

> **Maintenance notice:** this repository is being actively rebuilt as a modern native PHP WebSocket and realtime chat library. The legacy 2016 implementation is preserved for historical reference, while the new Composer-based library is being implemented phase by phase.

PHPSockets With WebSockets was originally created in 2016 as an educational experiment showing how to build a WebSocket server with PHP sockets, without Node.js and without socket.io.

The project is now being progressively redesigned as a Composer package with a clean architecture, modern PHP support, examples, tests, storage adapters, CLI commands, optional Laravel integration, and a stronger chat-focused developer experience.

## Current status

The project is currently in the **Composer foundation phase**.

This means the repository already has the initial Composer package structure, PSR-4 namespace configuration, base configuration classes, PHPUnit setup, PHPStan setup, PHP CS Fixer setup, and GitHub Actions workflow.

The modern WebSocket runtime, global chat examples, callback-based MediumChat example, and first direct private chat example are now implemented.

## Installation for development

Clone the repository and install dependencies:

```bash
composer install
```

Validate the Composer package:

```bash
composer validate --strict
```

Run the test suite:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

Check code style:

```bash
composer cs:check
```

Run all quality checks:

```bash
composer quality
```

Fix code style automatically:

```bash
composer cs:fix
```

## Running the EasyChat example

Start the WebSocket server:

```bash
php examples/easy-chat/server.php
```

Open a second terminal and start the browser UI:

```bash
php -S 127.0.0.1:8000 -t examples/easy-chat/public
```

Then open:

```txt
http://127.0.0.1:8000
```

## Running the MediumChat example

Start the WebSocket server:

```bash
php examples/medium-chat/server.php
```

Open a second terminal and start the browser UI:

```bash
php -S 127.0.0.1:8001 -t examples/medium-chat/public
```

Then open:

```txt
http://127.0.0.1:8001
```

MediumChat demonstrates high-level callbacks such as `user.joined`, `user.left`, `message.received`, and `room.created`, plus low-level socket callbacks such as `open`, `close`, and `error`.

EasyChat and MediumChat also include typing indicators and simple message status receipts for sent, received, and read states.

## Running the PrivateChat example

Start the WebSocket server:

```bash
php examples/private-chat/server.php
```

Open a second terminal and start the browser UI:

```bash
php -S 127.0.0.1:8002 -t examples/private-chat/public
```

Then open:

```txt
http://127.0.0.1:8002
```

PrivateChat demonstrates global chat plus direct 1:1 private conversations. A direct message is delivered only to the sender and the selected recipient.

## Requirements

The modern version targets:

- PHP 8.2 or higher.
- `ext-sockets`.
- `ext-json`.
- Composer.

Optional future features may require:

- `ext-pdo` for SQL storage adapters.
- Laravel packages for optional Laravel integration.

## Namespace

The modern library uses the following namespace:

```txt
Micilini\PhpSockets\
```

The current public entry point is:

```php
use Micilini\PhpSockets\WebSocket;

echo WebSocket::version();
```

## Current structure

```txt
src/
  WebSocket.php
  Config/
    ServerConfig.php
    ChatConfig.php

tests/
  Unit/
    SanityTest.php

legacy/
  EasyChat/
  MediumChat/
  README-2016.md
  NOTES.md
```

## Legacy code

The original 2016 implementation is preserved here:

```txt
legacy/EasyChat
legacy/MediumChat
legacy/README-2016.md
```

`EasyChat` contains the beginner-friendly version of the original global chat.

`MediumChat` contains the more advanced object-oriented version with callbacks and a better separation between the WebSocket server and the chat behavior.

The legacy implementation is kept for historical and educational purposes only. The modern library will be implemented separately and should not depend on the old code structure.

## Why the legacy code was moved

The old project was designed for PHP 5 and browser-based local testing. At that time, the server could be started by opening `server.php` in the browser or by running it manually.

The new version will not require keeping a browser tab open to run the WebSocket server.

The future official runtime will be based on CLI execution.

## Modernization goals

The new implementation will be developed gradually and will include:

- Composer package support.
- PHP 8.2+ support.
- PSR-4 namespaces.
- Native WebSocket protocol implementation.
- Server runtime with connection lifecycle events.
- Chat core with unique display names.
- Global chat example.
- Medium chat example with callbacks.
- Private chat example.
- Private group rooms with selected online participants.
- Storage adapters.
- CLI commands.
- Laravel integration.
- Tests, static analysis, and CI.

## Roadmap phases

The project is being implemented phase by phase:

```txt
Phase 00: Legacy preservation.
Phase 01: Composer foundation, namespace, quality tools, and CI.
Phase 02: WebSocket protocol core.
Phase 03: Server runtime and events.
Phase 04: Chat core and unique usernames.
Phase 05: Modern EasyChat example.
Phase 06: MediumChat example with callbacks.
Phase 07: Private direct messaging.
Phase 08: Private group rooms.
Phase 09: Storage adapters and migrations.
Phase 10: CLI runtime commands.
Phase 11: Small attachments and emoji-safe payloads.
Phase 12: Bot hooks and automation events.
Phase 13: Laravel integration.
Phase 14: Release documentation and Packagist preparation.
```

## Production readiness

This package is not production-ready yet.

The repository is currently being rebuilt. The modern WebSocket protocol, server runtime, chat system, examples, storage adapters, CLI commands, and Laravel integration will be added in future phases.

## Important note

The goal is not only to restore an old chat demo.

The goal is to transform PHPSockets With WebSockets into a modern, educational, extensible, native PHP realtime library.
