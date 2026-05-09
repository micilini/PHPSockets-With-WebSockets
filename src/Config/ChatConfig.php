<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Config;

use InvalidArgumentException;

final readonly class ChatConfig
{
    public function __construct(
        public int $maxDisplayNameLength,
        public int $maxRoomNameLength,
        public int $maxPrivateGroupMembers,
        public bool $allowGuestSessions,
        public int $historyLimit,
        public int $maxAttachmentBytes,
        public int $maxAttachmentFileNameLength,
        /**
         * @var list<string>
         */
        public array $allowedAttachmentMimeTypes,
    ) {
        if ($this->maxDisplayNameLength < 1) {
            throw new InvalidArgumentException('Maximum display name length must be greater than zero.');
        }

        if ($this->maxRoomNameLength < 1) {
            throw new InvalidArgumentException('Maximum room name length must be greater than zero.');
        }

        if ($this->maxPrivateGroupMembers < 2) {
            throw new InvalidArgumentException('Maximum private group members must be at least 2.');
        }

        if ($this->historyLimit < 0) {
            throw new InvalidArgumentException('History limit cannot be negative.');
        }

        if ($this->maxAttachmentBytes < 1) {
            throw new InvalidArgumentException('Maximum attachment size must be greater than zero.');
        }

        if ($this->maxAttachmentFileNameLength < 1) {
            throw new InvalidArgumentException('Maximum attachment file name length must be greater than zero.');
        }

        if ($this->allowedAttachmentMimeTypes === []) {
            throw new InvalidArgumentException('At least one attachment MIME type must be allowed.');
        }
    }

    /**
     * @param list<string>|null $allowedAttachmentMimeTypes
     */
    public static function new(
        int $maxDisplayNameLength = 40,
        int $maxRoomNameLength = 80,
        int $maxPrivateGroupMembers = 20,
        bool $allowGuestSessions = true,
        int $historyLimit = 50,
        int $maxAttachmentBytes = 524288,
        int $maxAttachmentFileNameLength = 180,
        ?array $allowedAttachmentMimeTypes = null,
    ): self {
        return new self(
            maxDisplayNameLength: $maxDisplayNameLength,
            maxRoomNameLength: $maxRoomNameLength,
            maxPrivateGroupMembers: $maxPrivateGroupMembers,
            allowGuestSessions: $allowGuestSessions,
            historyLimit: $historyLimit,
            maxAttachmentBytes: $maxAttachmentBytes,
            maxAttachmentFileNameLength: $maxAttachmentFileNameLength,
            allowedAttachmentMimeTypes: $allowedAttachmentMimeTypes ?? [
                'image/png',
                'image/jpeg',
                'image/gif',
                'application/pdf',
                'text/plain',
            ],
        );
    }
}
