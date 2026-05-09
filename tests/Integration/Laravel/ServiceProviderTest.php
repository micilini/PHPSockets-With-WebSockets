<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Integration\Laravel;

use Micilini\PhpSockets\Chat\ChatServer;
use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\Laravel\PhpSocketsFacade;
use Micilini\PhpSockets\Laravel\PhpSocketsManager;
use Micilini\PhpSockets\Laravel\PhpSocketsServiceProvider;
use Orchestra\Testbench\TestCase;

final class ServiceProviderTest extends TestCase
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

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'PhpSockets' => PhpSocketsFacade::class,
        ];
    }

    public function testItRegistersConfiguration(): void
    {
        self::assertSame('127.0.0.1', config('phpsockets.server.host'));
        self::assertSame(8080, config('phpsockets.server.port'));
        self::assertSame(2 * 1024 * 1024, config('phpsockets.chat.max_attachment_bytes'));
    }

    public function testItRegistersManagerAndConfigs(): void
    {
        self::assertInstanceOf(PhpSocketsManager::class, $this->app->make(PhpSocketsManager::class));
        self::assertInstanceOf(ServerConfig::class, $this->app->make(ServerConfig::class));
        self::assertInstanceOf(ChatConfig::class, $this->app->make(ChatConfig::class));
    }

    public function testItRegistersChatServerBinding(): void
    {
        self::assertInstanceOf(ChatServer::class, $this->app->make(ChatServer::class));
        self::assertInstanceOf(ChatServer::class, $this->app->make('phpsockets'));
    }

    public function testFacadeResolvesChatServer(): void
    {
        self::assertInstanceOf(ChatServer::class, PhpSocketsFacade::getFacadeRoot());
    }
}
