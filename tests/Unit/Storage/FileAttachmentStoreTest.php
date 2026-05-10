<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Storage;

use Micilini\PhpSockets\Chat\Attachment;
use Micilini\PhpSockets\Exceptions\StorageException;
use Micilini\PhpSockets\Storage\File\FileAttachmentStore;
use PHPUnit\Framework\TestCase;

final class FileAttachmentStoreTest extends TestCase
{
    private ?string $directory = null;

    protected function tearDown(): void
    {
        if ($this->directory === null || !is_dir($this->directory)) {
            return;
        }

        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($this->directory);
        $this->directory = null;
    }

    public function testSavesContentMetadataAndFindsAttachment(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpsockets-attachment-test-' . bin2hex(random_bytes(6));
        $store = new FileAttachmentStore($this->directory);

        $attachment = $store->saveContent(
            messageId: 'msg_test',
            fileName: 'hello.txt',
            mimeType: 'text/plain',
            content: 'Hello attachment',
            metadata: ['clientMessageId' => 'client_file'],
        );
        $loaded = $store->find($attachment->id);

        self::assertInstanceOf(Attachment::class, $loaded);
        self::assertSame('msg_test', $loaded->messageId);
        self::assertSame('hello.txt', $loaded->fileName);
        self::assertSame('text/plain', $loaded->mimeType);
        self::assertSame(strlen('Hello attachment'), $loaded->sizeBytes);
        self::assertSame('client_file', $loaded->metadata['clientMessageId'] ?? null);
        self::assertFileExists($loaded->path);
        self::assertFileExists($this->directory . DIRECTORY_SEPARATOR . $attachment->id . '.json');
    }

    public function testConstructorFailsWhenAttachmentPathExistsAsFile(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpsockets-attachment-file-conflict-' . uniqid('', true);

        file_put_contents($path, 'not a directory');

        try {
            $this->expectException(StorageException::class);
            $this->expectExceptionMessage('Attachment path exists but is not a directory');

            new FileAttachmentStore($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
