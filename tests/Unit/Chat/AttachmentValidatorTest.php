<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Chat;

use Micilini\PhpSockets\Chat\AttachmentValidator;
use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Exceptions\InvalidPayloadException;
use PHPUnit\Framework\TestCase;

final class AttachmentValidatorTest extends TestCase
{
    public function testAcceptsPngWithinLimit(): void
    {
        $validator = new AttachmentValidator(ChatConfig::new());
        $content = 'small image bytes';
        $encoded = base64_encode($content);

        self::assertSame('photo.png', $validator->fileName('photo.png'));
        self::assertSame('image/png', $validator->mimeType('image/png'));
        self::assertSame(strlen($content), $validator->sizeBytes(strlen($content)));
        self::assertSame($content, $validator->decodedContent($encoded, strlen($content)));
    }

    public function testRejectsDisallowedMimeType(): void
    {
        $validator = new AttachmentValidator(ChatConfig::new());

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Attachment mimeType is not allowed.');

        $validator->mimeType('application/x-msdownload');
    }

    public function testAcceptsPdfMimeType(): void
    {
        $validator = new AttachmentValidator(ChatConfig::new());

        self::assertSame('application/pdf', $validator->mimeType('application/pdf'));
    }

    public function testAcceptsTwoMegabyteAttachmentSize(): void
    {
        $validator = new AttachmentValidator(ChatConfig::new());

        self::assertSame(2097152, $validator->sizeBytes(2097152));
    }

    public function testRejectsAttachmentLargerThanTwoMegabytes(): void
    {
        $validator = new AttachmentValidator(ChatConfig::new());

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Attachment exceeds the maximum allowed size.');

        $validator->sizeBytes(2097153);
    }

    public function testAcceptsSmallPdfBase64Content(): void
    {
        $validator = new AttachmentValidator(ChatConfig::new());
        $content = "%PDF-1.4\nsmall pdf\n";

        self::assertSame($content, $validator->decodedContent(base64_encode($content), strlen($content)));
    }

    public function testRejectsOversizedAttachment(): void
    {
        $validator = new AttachmentValidator(ChatConfig::new(maxAttachmentBytes: 4));

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Attachment exceeds the maximum allowed size.');

        $validator->sizeBytes(5);
    }

    public function testRejectsEmptyFileName(): void
    {
        $validator = new AttachmentValidator(ChatConfig::new());

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Attachment fileName cannot be empty.');

        $validator->fileName('   ');
    }

    public function testSanitizesDangerousFileName(): void
    {
        $validator = new AttachmentValidator(ChatConfig::new());

        self::assertSame('evil_name.txt', $validator->fileName('../../evil:name.txt'));
    }

    public function testRejectsInvalidBase64(): void
    {
        $validator = new AttachmentValidator(ChatConfig::new());

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Attachment contentBase64 is invalid.');

        $validator->decodedContent('not base64!@#', 10);
    }

    public function testRejectsBase64SizeMismatch(): void
    {
        $validator = new AttachmentValidator(ChatConfig::new());

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Attachment size does not match decoded content.');

        $validator->decodedContent(base64_encode('abc'), 4);
    }
}
