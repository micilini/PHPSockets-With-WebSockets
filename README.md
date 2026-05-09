# PHPSockets With WebSockets

PHPSockets With WebSockets is being reborn as a modern native PHP WebSocket and realtime chat library.

The original project was created in 2016 as a simple educational experiment showing how to build a WebSocket server with PHP sockets, without Node.js and without socket.io.

This repository is now being progressively redesigned as a Composer package with a clean architecture, modern PHP support, examples, tests, storage adapters, CLI commands, Laravel integration, and a stronger chat-focused developer experience.

## Current status

This repository is currently in the legacy preservation phase.

The original 2016 implementation has been moved to the `legacy/` directory so it can be preserved as historical reference while the new library is built from scratch in future phases.

## Legacy code

The original code is available here:

```txt
legacy/EasyChat
legacy/MediumChat
legacy/README-2016.md
```

`EasyChat` contains the beginner-friendly version of the original global chat.

`MediumChat` contains the more advanced object-oriented version with callbacks and a better separation between the WebSocket server and the chat behavior.

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

## Roadmap

Implementation will follow the project roadmap phase by phase.

The first phase preserves the legacy code and creates a clean baseline.

Future phases will introduce Composer, source code structure, WebSocket protocol handling, server runtime, chat features, examples, persistence, CLI tooling, and release documentation.

## Important note

The legacy implementation is kept for historical and educational purposes.

The new library will be implemented separately and should not depend on the old code structure.
