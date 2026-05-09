<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Server;

use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\Connection\Connection;
use Micilini\PhpSockets\Contracts\EventDispatcherInterface;
use Micilini\PhpSockets\Events\Event;
use Micilini\PhpSockets\Events\MessageReceived;
use Micilini\PhpSockets\Protocol\Frame;
use Micilini\PhpSockets\Protocol\FrameCodec;
use Micilini\PhpSockets\Protocol\Opcode;
use Micilini\PhpSockets\Server\ServerRuntime;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

final class ServerRuntimeFragmentationTest extends TestCase
{
    public function testFragmentedTextFramesAreReassembledBeforeDispatch(): void
    {
        $dispatcher = new class () implements EventDispatcherInterface {
            /**
             * @var list<Event>
             */
            public array $events = [];

            public function listen(string $eventName, callable $listener): void
            {
            }

            public function dispatch(Event $event): void
            {
                $this->events[] = $event;
            }
        };
        $runtime = new ServerRuntime(ServerConfig::new(maxPayloadBytes: 1024), $dispatcher);
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($socket === false) {
            throw new RuntimeException('Failed to create test socket.');
        }

        try {
            $connection = new Connection('conn_test', $socket, new FrameCodec());
            $handleFrame = new ReflectionMethod($runtime, 'handleFrame');

            $handleFrame->invoke($runtime, $connection, new Frame(false, Opcode::TEXT, 'Hello '));
            $handleFrame->invoke($runtime, $connection, new Frame(true, Opcode::CONTINUATION, 'world'));

            self::assertCount(1, $dispatcher->events);
            self::assertInstanceOf(MessageReceived::class, $dispatcher->events[0]);
            self::assertSame(Opcode::TEXT, $dispatcher->events[0]->frame->opcode);
            self::assertTrue($dispatcher->events[0]->frame->fin);
            self::assertSame('Hello world', $dispatcher->events[0]->frame->payload);
        } finally {
            socket_close($socket);
        }
    }
}
