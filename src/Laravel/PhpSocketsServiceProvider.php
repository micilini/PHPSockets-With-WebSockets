<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Laravel;

use Illuminate\Support\ServiceProvider;
use Micilini\PhpSockets\Chat\ChatServer;
use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\Laravel\Commands\MigrateCommand;
use Micilini\PhpSockets\Laravel\Commands\ServeCommand;
use Micilini\PhpSockets\Laravel\Commands\StatusCommand;

final class PhpSocketsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/phpsockets.php', 'phpsockets');

        $this->app->singleton(PhpSocketsManager::class, static function ($app): PhpSocketsManager {
            return new PhpSocketsManager($app['config']);
        });

        $this->app->alias(PhpSocketsManager::class, 'phpsockets.manager');

        $this->app->bind(ServerConfig::class, static function ($app): ServerConfig {
            return $app->make(PhpSocketsManager::class)->serverConfig();
        });

        $this->app->bind(ChatConfig::class, static function ($app): ChatConfig {
            return $app->make(PhpSocketsManager::class)->chatConfig();
        });

        $this->app->bind(ChatServer::class, static function ($app): ChatServer {
            return $app->make(PhpSocketsManager::class)->server();
        });

        $this->app->alias(ChatServer::class, 'phpsockets');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/phpsockets.php' => config_path('phpsockets.php'),
        ], 'phpsockets-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ServeCommand::class,
                MigrateCommand::class,
                StatusCommand::class,
            ]);
        }
    }
}
