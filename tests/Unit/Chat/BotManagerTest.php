<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Chat;

use Micilini\PhpSockets\Chat\Bot\BotContext;
use Micilini\PhpSockets\Chat\Bot\BotManager;
use Micilini\PhpSockets\Chat\Bot\BotResponse;
use Micilini\PhpSockets\Chat\ChatMessage;
use Micilini\PhpSockets\Chat\Room;
use Micilini\PhpSockets\Contracts\BotInterface;
use PHPUnit\Framework\TestCase;

final class BotManagerTest extends TestCase
{
    public function testRegisterAddsBotAndReturnsItInAll(): void
    {
        $bot = $this->echoBot();
        $manager = new BotManager();

        self::assertFalse($manager->hasBots());
        self::assertSame($manager, $manager->register($bot));
        self::assertTrue($manager->hasBots());
        self::assertSame([$bot], $manager->all());
    }

    public function testHandleReturnsMatchingBotResponse(): void
    {
        $manager = new BotManager();
        $manager->register($this->echoBot());

        $responses = $manager->handle($this->context('/echo Hello'));

        self::assertCount(1, $responses);
        self::assertSame('Test Echo Bot', $responses[0]['bot']->name());
        self::assertSame('Hello', $responses[0]['response']->text);
    }

    public function testHandleIgnoresNullAndEmptyResponses(): void
    {
        $manager = new BotManager();
        $manager
            ->register($this->echoBot())
            ->register($this->emptyBot());

        self::assertSame([], $manager->handle($this->context('/noop')));
    }

    private function context(string $text): BotContext
    {
        return new BotContext(
            message: ChatMessage::text('global', 'usr_william', $text),
            room: Room::global(),
            sender: null,
            scope: 'global',
            recipientUserIds: [],
        );
    }

    private function echoBot(): BotInterface
    {
        return new class () implements BotInterface {
            public function name(): string
            {
                return 'Test Echo Bot';
            }

            public function handle(BotContext $context): ?BotResponse
            {
                $text = trim($context->text());

                if (!str_starts_with($text, '/echo ')) {
                    return null;
                }

                return BotResponse::text(substr($text, 6));
            }
        };
    }

    private function emptyBot(): BotInterface
    {
        return new class () implements BotInterface {
            public function name(): string
            {
                return 'Empty Bot';
            }

            public function handle(BotContext $context): ?BotResponse
            {
                if ($context->text() === '/never') {
                    return null;
                }

                return BotResponse::text('   ');
            }
        };
    }
}
