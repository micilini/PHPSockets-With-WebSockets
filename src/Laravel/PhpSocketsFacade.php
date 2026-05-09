<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Laravel;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Micilini\PhpSockets\Chat\Bot\BotManager bots()
 * @method static \Micilini\PhpSockets\Chat\ChatKernel kernel()
 * @method static void run()
 * @method static void stop()
 * @method static \Micilini\PhpSockets\Server\WebSocketServer webSocketServer()
 *
 * @see \Micilini\PhpSockets\Chat\ChatServer
 */
final class PhpSocketsFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'phpsockets';
    }
}
