<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Examples;

use PHPUnit\Framework\TestCase;

final class ExampleServerAutoloadTest extends TestCase
{
    public function testExamplesHaveSharedBootstrapFile(): void
    {
        self::assertFileExists(__DIR__ . '/../../../examples/bootstrap.php');
    }

    public function testExampleServersUseSharedBootstrapInsteadOfLocalVendorAutoload(): void
    {
        $serverFiles = [
            __DIR__ . '/../../../examples/easy-chat/server.php',
            __DIR__ . '/../../../examples/medium-chat/server.php',
            __DIR__ . '/../../../examples/private-chat/server.php',
        ];

        foreach ($serverFiles as $serverFile) {
            self::assertFileExists($serverFile);

            $contents = (string) file_get_contents($serverFile);

            self::assertStringContainsString(
                "require __DIR__ . '/../bootstrap.php';",
                $contents,
                "{$serverFile} should use the shared examples bootstrap.",
            );

            self::assertStringNotContainsString(
                '/../../vendor/autoload.php',
                $contents,
                "{$serverFile} should not assume a local package vendor directory.",
            );
        }
    }

    public function testSharedBootstrapSupportsRepositoryAndComposerInstallPaths(): void
    {
        $bootstrap = (string) file_get_contents(__DIR__ . '/../../../examples/bootstrap.php');

        self::assertStringContainsString("__DIR__ . '/../vendor/autoload.php'", $bootstrap);
        self::assertStringContainsString("__DIR__ . '/../../../autoload.php'", $bootstrap);
        self::assertStringContainsString("getcwd() . '/vendor/autoload.php'", $bootstrap);
    }
}
