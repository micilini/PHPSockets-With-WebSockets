<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Laravel\Commands;

use Illuminate\Console\Command;
use Micilini\PhpSockets\Laravel\PhpSocketsManager;

final class StatusCommand extends Command
{
    protected $signature = 'phpsockets:status';

    protected $description = 'Show PHPSockets Laravel configuration status.';

    public function handle(PhpSocketsManager $manager): int
    {
        $serverConfig = $manager->serverConfig();
        $chatConfig = $manager->chatConfig();

        $this->components->info('PHPSockets is installed.');

        $this->table(['Option', 'Value'], [
            ['Host', $serverConfig->host],
            ['Port', (string) $serverConfig->port],
            ['Max payload bytes', (string) $serverConfig->maxPayloadBytes],
            ['Connection limit', (string) $serverConfig->connectionLimit],
            ['Debug logs', $serverConfig->enableDebugLogs ? 'yes' : 'no'],
            ['History limit', (string) $chatConfig->historyLimit],
            ['Max attachment bytes', (string) $chatConfig->maxAttachmentBytes],
            ['Storage driver', $manager->storageDriver()],
        ]);

        return self::SUCCESS;
    }
}
