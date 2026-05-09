<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Contracts;

use Micilini\PhpSockets\Chat\Attachment;

interface AttachmentStoreInterface
{
    public function save(Attachment $attachment): Attachment;

    public function find(string $attachmentId): ?Attachment;
}
