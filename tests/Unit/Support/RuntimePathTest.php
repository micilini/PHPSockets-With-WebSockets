<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Support;

use Micilini\PhpSockets\Support\RuntimePath;
use PHPUnit\Framework\TestCase;

final class RuntimePathTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('PHPSOCKETS_ATTACHMENT_DIR');

        parent::tearDown();
    }

    public function testAttachmentsDirectoryUsesEnvironmentOverride(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'custom-phpsockets-attachments';

        putenv('PHPSOCKETS_ATTACHMENT_DIR=' . $path);

        self::assertSame($path, RuntimePath::attachmentsDirectory());
    }

    public function testAttachmentsDirectoryDefaultsToProjectLocalDirectory(): void
    {
        putenv('PHPSOCKETS_ATTACHMENT_DIR');

        $path = RuntimePath::attachmentsDirectory();

        self::assertStringContainsString('.phpsockets', $path);
        self::assertStringContainsString('attachments', $path);
        self::assertStringStartsWith((string) getcwd(), $path);
    }
}
