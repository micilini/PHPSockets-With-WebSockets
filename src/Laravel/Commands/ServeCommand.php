<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Laravel\Commands;

use Illuminate\Console\Command;
use Micilini\PhpSockets\Laravel\PhpSocketsManager;
use Throwable;

final class ServeCommand extends Command
{
    protected $signature = 'phpsockets:serve
        {--host= : Override configured WebSocket host}
        {--port= : Override configured WebSocket port}
        {--debug : Enable debug logs for this run}';

    protected $description = 'Start the PHPSockets WebSocket chat server from Laravel.';

    public function handle(PhpSocketsManager $manager): int
    {
        $overrides = [];
        $host = $this->option('host');

        if (is_string($host) && $host !== '') {
            $overrides['host'] = $host;
        }

        $port = $this->option('port');

        if (is_string($port) && $port !== '') {
            $overrides['port'] = (int) $port;
        }

        if ((bool) $this->option('debug')) {
            $overrides['debug'] = true;
        }

        try {
            $serverConfig = $manager->serverConfig($overrides);
            $server = $manager->server($overrides);

            $this->components->info("Starting PHPSockets on {$serverConfig->host}:{$serverConfig->port}");
            $this->line('Press CTRL+C to stop.');

            $server->run();

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
