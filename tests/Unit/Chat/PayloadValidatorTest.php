<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Chat;

use Micilini\PhpSockets\Chat\MessageEnvelope;
use Micilini\PhpSockets\Chat\PayloadValidator;
use Micilini\PhpSockets\Exceptions\InvalidPayloadException;
use PHPUnit\Framework\TestCase;

final class PayloadValidatorTest extends TestCase
{
    public function testJoinDisplayNameCanBeRead(): void
    {
        $validator = new PayloadValidator();
        $envelope = new MessageEnvelope('auth.join', ['displayName' => 'William']);

        self::assertSame('William', $validator->displayName($envelope));
    }

    public function testEmptyMessageTextIsRejected(): void
    {
        $validator = new PayloadValidator();
        $envelope = new MessageEnvelope('message.global', ['text' => '   ']);

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Message text cannot be empty.');

        $validator->text($envelope);
    }

    public function testParticipantUserIdsAreNormalized(): void
    {
        $validator = new PayloadValidator();
        $envelope = new MessageEnvelope('room.create', [
            'participantUserIds' => ['usr_1', 'usr_1', 'usr_2'],
        ]);

        self::assertSame(['usr_1', 'usr_2'], $validator->participantUserIds($envelope));
    }

    public function testUnsupportedMessageTypeIsRejected(): void
    {
        $validator = new PayloadValidator();
        $envelope = new MessageEnvelope('unknown.event');

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Unsupported message type.');

        $validator->assertEnvelope($envelope);
    }

    public function testMessageReadTypeIsAccepted(): void
    {
        $validator = new PayloadValidator();
        $envelope = new MessageEnvelope('message.read', ['messageId' => 'msg_123']);

        $validator->assertEnvelope($envelope);

        self::assertSame('msg_123', $validator->messageId($envelope));
    }

    public function testEmptyMessageIdIsRejected(): void
    {
        $validator = new PayloadValidator();
        $envelope = new MessageEnvelope('message.read', ['messageId' => '   ']);

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Payload field messageId is required.');

        $validator->messageId($envelope);
    }

    public function testClientMessageIdIsNormalized(): void
    {
        $validator = new PayloadValidator();
        $envelope = new MessageEnvelope('message.global', ['clientMessageId' => ' client_123 ']);

        self::assertSame('client_123', $validator->clientMessageId($envelope));
    }
}
