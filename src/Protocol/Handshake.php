<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Protocol;

final class Handshake
{
    private const WEBSOCKET_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    public static function acceptKey(string $key): string
    {
        return base64_encode(sha1($key . self::WEBSOCKET_GUID, true));
    }

    /**
     * @return array<string, string>
     */
    public static function parseRequestHeaders(string $request): array
    {
        $headers = [];
        $lines = preg_split('/\r\n|\n|\r/', $request) ?: [];

        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    public static function validateRequest(string $request): array
    {
        $headers = self::parseRequestHeaders($request);

        if (strtolower($headers['upgrade'] ?? '') !== 'websocket') {
            throw new ProtocolException('Invalid WebSocket upgrade header.');
        }

        if (!str_contains(strtolower($headers['connection'] ?? ''), 'upgrade')) {
            throw new ProtocolException('Invalid WebSocket connection header.');
        }

        $key = $headers['sec-websocket-key'] ?? '';

        if (!self::isValidClientKey($key)) {
            throw new ProtocolException('Invalid WebSocket client key.');
        }

        if (($headers['sec-websocket-version'] ?? '13') !== '13') {
            throw new ProtocolException('Unsupported WebSocket version.');
        }

        return $headers;
    }

    public static function response(string $request): string
    {
        $headers = self::validateRequest($request);
        $accept = self::acceptKey($headers['sec-websocket-key']);

        return "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Accept: {$accept}\r\n\r\n";
    }

    private static function isValidClientKey(string $key): bool
    {
        if ($key === '') {
            return false;
        }

        $decoded = base64_decode($key, true);

        return is_string($decoded) && strlen($decoded) === 16;
    }
}
