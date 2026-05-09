# Legacy Notes

This document records known limitations and historical details from the original 2016 implementation.

The legacy code is preserved for reference only. The modern library will be implemented separately in future phases.

## EasyChat

- `server.php` uses `socket_select` with reference-style arguments that may be incompatible with modern PHP versions.
- `socket_recv` is called with `null` as the fourth argument in one place, while modern PHP expects an integer flags value.
- Client messages are rendered with `innerHTML`, which can create XSS risks when rendering user-controlled content.
- The chat works as a global broadcast without user identity.
- There is no structured session model.
- There is no username validation.
- There is no unique display name rule.
- There is no room model.
- There is no private messaging.
- There is no private group room support.
- There is no ping/pong lifecycle handling.
- There are no organized close codes.
- There is no payload validation.
- There is no rate limit.
- There are no automated tests.
- There is no Composer package structure.
- There is no namespace structure.

## MediumChat

- The project has a better object-oriented separation than EasyChat.
- The callback model is useful and should inspire the modern event API.
- The implementation still does not use namespaces.
- The implementation still does not use Composer.
- The implementation stores connected clients in internal arrays.
- There are no contracts or interfaces for extension points.
- There is no standalone package structure.
- There are no automated tests.
- There is no static analysis configuration.
- There is no modern CI pipeline.

## Historical value

The original project demonstrated that PHP sockets could be used to build a WebSocket-based chat without Node.js and without socket.io.

The new library should preserve that spirit while adopting a modern architecture, safer defaults, stronger examples, and a better developer experience.