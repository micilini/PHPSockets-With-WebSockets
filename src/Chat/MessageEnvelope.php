<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use JsonException;
use Micilini\PhpSockets\Exceptions\InvalidPayloadException;

final readonly class MessageEnvelope
{
    public string $id;

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $type,
        public array $payload = [],
        public array $meta = [],
        ?string $id = null,
    ) {
        if ($this->type === '') {
            throw new InvalidPayloadException('Message type cannot be empty.');
        }

        $this->id = $id ?? self::generateId();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function server(string $type, array $payload = []): self
    {
        return new self($type, $payload);
    }

    public static function fromJson(string $json): self
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidPayloadException('Invalid JSON payload.', previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new InvalidPayloadException('Message payload must be a JSON object.');
        }

        $type = $decoded['type'] ?? null;

        if (!is_string($type) || trim($type) === '') {
            throw new InvalidPayloadException('Message type is required.');
        }

        $payload = self::objectValue($decoded['payload'] ?? []);
        $meta = self::objectValue($decoded['meta'] ?? []);
        $id = $decoded['id'] ?? null;

        return new self(
            type: trim($type),
            payload: $payload,
            meta: $meta,
            id: is_string($id) && $id !== '' ? $id : null,
        );
    }

    public function toJson(): string
    {
        try {
            return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new InvalidPayloadException('Failed to encode message payload.', previous: $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'payload' => $this->payload,
            'meta' => $this->meta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function objectValue(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            throw new InvalidPayloadException('Message payload fields must be JSON objects.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    private static function generateId(): string
    {
        return 'evt_' . bin2hex(random_bytes(16));
    }
}
