<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use Micilini\PhpSockets\Exceptions\InvalidPayloadException;

final class PayloadValidator
{
    /**
     * @var list<string>
     */
    private array $allowedTypes = [
        'auth.join',
        'attachment.prepare',
        'message.global',
        'message.direct',
        'message.file',
        'message.read',
        'room.create',
        'room.message',
        'typing.start',
        'typing.stop',
    ];

    public function assertEnvelope(MessageEnvelope $envelope): void
    {
        if (!in_array($envelope->type, $this->allowedTypes, true)) {
            throw new InvalidPayloadException('Unsupported message type.');
        }
    }

    public function displayName(MessageEnvelope $envelope): string
    {
        return $this->requiredString($envelope, 'displayName');
    }

    public function text(MessageEnvelope $envelope): string
    {
        $value = $envelope->payload['text'] ?? null;

        if (!is_string($value)) {
            throw new InvalidPayloadException('Payload field text is required.');
        }

        $text = trim($value);

        if ($text === '') {
            throw new InvalidPayloadException('Message text cannot be empty.');
        }

        return $text;
    }

    public function targetUserId(MessageEnvelope $envelope): string
    {
        return $this->requiredString($envelope, 'toUserId');
    }

    public function roomId(MessageEnvelope $envelope): string
    {
        return $this->requiredString($envelope, 'roomId');
    }

    public function optionalRoomId(MessageEnvelope $envelope): ?string
    {
        $value = $envelope->payload['roomId'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidPayloadException('Payload field roomId must be a string.');
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    public function messageId(MessageEnvelope $envelope): string
    {
        return $this->requiredString($envelope, 'messageId');
    }

    public function clientMessageId(MessageEnvelope $envelope): ?string
    {
        $value = $envelope->payload['clientMessageId'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidPayloadException('Payload field clientMessageId must be a string.');
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (strlen($value) > 120) {
            throw new InvalidPayloadException('Payload field clientMessageId is too long.');
        }

        return $value;
    }

    public function roomName(MessageEnvelope $envelope): ?string
    {
        $name = $envelope->payload['name'] ?? null;

        if ($name === null) {
            return null;
        }

        if (!is_string($name)) {
            throw new InvalidPayloadException('Room name must be a string.');
        }

        return trim($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function attachmentPayload(MessageEnvelope $envelope): array
    {
        $value = $envelope->payload['attachment'] ?? null;

        if (!is_array($value)) {
            throw new InvalidPayloadException('Payload field attachment is required.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @return list<string>
     */
    public function participantUserIds(MessageEnvelope $envelope): array
    {
        $value = $envelope->payload['participantUserIds'] ?? null;

        if (!is_array($value)) {
            throw new InvalidPayloadException('Private room participants are required.');
        }

        $userIds = [];

        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                throw new InvalidPayloadException('Participant user ids must be non-empty strings.');
            }

            $userIds[] = trim($item);
        }

        $userIds = array_values(array_unique($userIds));

        if ($userIds === []) {
            throw new InvalidPayloadException('Private room requires at least one participant.');
        }

        return $userIds;
    }

    private function requiredString(MessageEnvelope $envelope, string $key): string
    {
        $value = $envelope->payload[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidPayloadException("Payload field {$key} is required.");
        }

        return trim($value);
    }
}
