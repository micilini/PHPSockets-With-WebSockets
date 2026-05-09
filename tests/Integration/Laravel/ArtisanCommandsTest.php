<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Integration\Laravel;

use Micilini\PhpSockets\Laravel\PhpSocketsServiceProvider;
use Orchestra\Testbench\TestCase;

final class ArtisanCommandsTest extends TestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            PhpSocketsServiceProvider::class,
        ];
    }

    public function testStatusCommandRuns(): void
    {
        $this->artisan('phpsockets:status')
            ->assertExitCode(0);
    }

    public function testMemoryMigrateCommandDoesNothingSuccessfully(): void
    {
        config()->set('phpsockets.storage.driver', 'memory');

        $this->artisan('phpsockets:migrate')
            ->assertExitCode(0);
    }

    public function testSqliteMigrateCommandCreatesDatabase(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite extension is not available.');
        }

        $database = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpsockets_laravel_' . bin2hex(random_bytes(6)) . '.sqlite';

        config()->set('phpsockets.storage.driver', 'sqlite');
        config()->set('phpsockets.storage.database', $database);

        $this->artisan('phpsockets:migrate', [
            '--driver' => 'sqlite',
            '--database' => $database,
        ])->assertExitCode(0);

        self::assertFileExists($database);

        @unlink($database);
    }
}
