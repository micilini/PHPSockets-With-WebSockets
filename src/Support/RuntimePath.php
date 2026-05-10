<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Support;

final class RuntimePath
{
    public static function attachmentsDirectory(): string
    {
        $configuredPath = getenv('PHPSOCKETS_ATTACHMENT_DIR');

        if (is_string($configuredPath) && trim($configuredPath) !== '') {
            return self::normalize($configuredPath);
        }

        $workingDirectory = getcwd();

        if (is_string($workingDirectory) && $workingDirectory !== '') {
            return self::join($workingDirectory, '.phpsockets', 'attachments');
        }

        return self::join(
            sys_get_temp_dir(),
            'phpsockets-' . substr(hash('sha256', __DIR__), 0, 12),
            'attachments',
        );
    }

    private static function join(string $basePath, string ...$segments): string
    {
        $path = rtrim($basePath, '/\\');

        if ($path === '') {
            $path = DIRECTORY_SEPARATOR;
        }

        foreach ($segments as $segment) {
            $path .= DIRECTORY_SEPARATOR . trim($segment, '/\\');
        }

        return $path;
    }

    private static function normalize(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path));
        $path = rtrim($path, DIRECTORY_SEPARATOR);

        return $path !== '' ? $path : DIRECTORY_SEPARATOR;
    }
}
