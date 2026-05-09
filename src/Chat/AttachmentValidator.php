<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Exceptions\InvalidPayloadException;

final readonly class AttachmentValidator
{
    public function __construct(private ChatConfig $config)
    {
    }

    public function fileName(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidPayloadException('Attachment fileName is required.');
        }

        $fileName = trim($value);

        if ($fileName === '') {
            throw new InvalidPayloadException('Attachment fileName cannot be empty.');
        }

        $fileName = basename(str_replace('\\', '/', $fileName));
        $fileName = preg_replace('/[^a-zA-Z0-9._ -]/', '_', $fileName);

        if (!is_string($fileName) || trim($fileName) === '') {
            throw new InvalidPayloadException('Attachment fileName is invalid.');
        }

        if (strlen($fileName) > $this->config->maxAttachmentFileNameLength) {
            throw new InvalidPayloadException('Attachment fileName is too long.');
        }

        return $fileName;
    }

    public function mimeType(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidPayloadException('Attachment mimeType is required.');
        }

        $mimeType = strtolower(trim($value));

        if (!in_array($mimeType, $this->config->allowedAttachmentMimeTypes, true)) {
            throw new InvalidPayloadException('Attachment mimeType is not allowed.');
        }

        return $mimeType;
    }

    public function sizeBytes(mixed $value): int
    {
        if (!is_int($value)) {
            throw new InvalidPayloadException('Attachment sizeBytes is required.');
        }

        if ($value <= 0) {
            throw new InvalidPayloadException('Attachment sizeBytes must be greater than zero.');
        }

        if ($value > $this->config->maxAttachmentBytes) {
            throw new InvalidPayloadException('Attachment exceeds the maximum allowed size.');
        }

        return $value;
    }

    public function decodedContent(mixed $value, int $expectedSizeBytes): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidPayloadException('Attachment contentBase64 is required.');
        }

        $decoded = base64_decode($value, true);

        if (!is_string($decoded)) {
            throw new InvalidPayloadException('Attachment contentBase64 is invalid.');
        }

        $actualSize = strlen($decoded);

        if ($actualSize !== $expectedSizeBytes) {
            throw new InvalidPayloadException('Attachment size does not match decoded content.');
        }

        if ($actualSize > $this->config->maxAttachmentBytes) {
            throw new InvalidPayloadException('Attachment exceeds the maximum allowed size.');
        }

        return $decoded;
    }
}
