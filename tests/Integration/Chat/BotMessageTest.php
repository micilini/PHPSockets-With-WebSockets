<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Integration\Chat;

use Micilini\PhpSockets\Chat\Bot\BotContext;
use Micilini\PhpSockets\Chat\Bot\BotResponse;
use Micilini\PhpSockets\Chat\ChatServer;
use Micilini\PhpSockets\Chat\Room;
use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\Connection\Connection;
use Micilini\PhpSockets\Contracts\BotInterface;
use Micilini\PhpSockets\Events\MessageReceived;
use Micilini\PhpSockets\Protocol\Frame;
use Micilini\PhpSockets\Protocol\FrameCodec;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Socket;

final class BotMessageTest extends TestCase
{
    /**
     * @var list<Socket>
     */
    private array $sockets = [];

    protected function tearDown(): void
    {
        foreach ($this->sockets as $socket) {
            socket_close($socket);
        }

        $this->sockets = [];
    }

    public function testGlobalTextCommandGeneratesBotResponse(): void
    {
        $server = $this->serverWithEchoBot();
        [$connection, $socket] = $this->authenticatedConnection($server, 'conn_william', 'William');

        $this->drainAvailableEnvelopes($socket);

        $this->dispatchClientMessage($server, $connection, [
            'type' => 'message.global',
            'payload' => [
                'text' => '/echo Hello bot',
            ],
        ]);

        $botEnvelope = $this->receiveMessageEnvelopeWithKind($socket, 'bot');
        $message = $botEnvelope['payload']['message'] ?? null;

        self::assertIsArray($message);
        self::assertSame('Hello bot', $message['body'] ?? null);
        self::assertSame('bot', $message['kind'] ?? null);
        self::assertSame(true, $message['metadata']['bot'] ?? null);
        self::assertSame('Test Echo Bot', $message['metadata']['botName'] ?? null);
    }

    public function testDirectBotResponseIsDeliveredOnlyToSenderAndRecipient(): void
    {
        $server = $this->serverWithEchoBot();
        [$williamConnection, $williamSocket] = $this->authenticatedConnection($server, 'conn_william', 'William');
        [$anaConnection, $anaSocket] = $this->authenticatedConnection($server, 'conn_ana', 'Ana');
        [, $brunoSocket] = $this->authenticatedConnection($server, 'conn_bruno', 'Bruno');

        $this->drainAvailableEnvelopes($williamSocket);
        $this->drainAvailableEnvelopes($anaSocket);
        $this->drainAvailableEnvelopes($brunoSocket);

        $this->dispatchClientMessage($server, $williamConnection, [
            'type' => 'message.direct',
            'payload' => [
                'toUserId' => $anaConnection->userId(),
                'text' => '/echo private',
            ],
        ]);

        self::assertSame('private', $this->botMessageBody($williamSocket));
        self::assertSame('private', $this->botMessageBody($anaSocket));
        self::assertFalse($this->hasBotMessage($this->drainAvailableEnvelopes($brunoSocket)));
    }

    public function testPrivateGroupBotResponseIsDeliveredOnlyToMembers(): void
    {
        $server = $this->serverWithEchoBot();
        [$williamConnection, $williamSocket] = $this->authenticatedConnection($server, 'conn_william', 'William');
        [$anaConnection, $anaSocket] = $this->authenticatedConnection($server, 'conn_ana', 'Ana');
        [, $carlaSocket] = $this->authenticatedConnection($server, 'conn_carla', 'Carla');

        $this->drainAvailableEnvelopes($williamSocket);
        $this->drainAvailableEnvelopes($anaSocket);
        $this->drainAvailableEnvelopes($carlaSocket);

        $this->dispatchClientMessage($server, $williamConnection, [
            'type' => 'room.create',
            'payload' => [
                'type' => Room::TYPE_PRIVATE_GROUP,
                'participantUserIds' => [$anaConnection->userId()],
            ],
        ]);

        $roomEnvelope = $this->receiveServerEnvelope($williamSocket, 'room.created');
        $roomId = (string) ($roomEnvelope['payload']['room']['id'] ?? '');

        $this->drainAvailableEnvelopes($anaSocket);
        $this->drainAvailableEnvelopes($carlaSocket);

        $this->dispatchClientMessage($server, $williamConnection, [
            'type' => 'room.message',
            'payload' => [
                'roomId' => $roomId,
                'text' => '/echo room',
            ],
        ]);

        self::assertSame('room', $this->botMessageBody($williamSocket));
        self::assertSame('room', $this->botMessageBody($anaSocket));
        self::assertFalse($this->hasBotMessage($this->drainAvailableEnvelopes($carlaSocket)));
    }

    public function testFileMessageDoesNotTriggerBotResponse(): void
    {
        $server = $this->serverWithEchoBot();
        [$connection, $socket] = $this->authenticatedConnection($server, 'conn_william', 'William');

        $this->drainAvailableEnvelopes($socket);

        $this->dispatchClientMessage($server, $connection, [
            'type' => 'message.file',
            'payload' => [
                'scope' => 'global',
                'attachment' => [
                    'fileName' => 'hello.txt',
                    'mimeType' => 'text/plain',
                    'sizeBytes' => 5,
                    'contentBase64' => base64_encode('hello'),
                ],
            ],
        ]);

        $envelopes = $this->receiveAvailableEnvelopes($socket, 200000);

        self::assertTrue($this->hasEnvelopeType($envelopes, 'message.received'));
        self::assertFalse($this->hasBotMessage($envelopes));
    }

    private function serverWithEchoBot(): ChatServer
    {
        $server = ChatServer::create(ServerConfig::new(), ChatConfig::new());
        $server->bots()->register($this->echoBot());

        return $server;
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

                return BotResponse::text(trim(substr($text, 6)));
            }
        };
    }

    /**
     * @return array{0: Connection, 1: Socket}
     */
    private function authenticatedConnection(ChatServer $server, string $id, string $displayName): array
    {
        [$connection, $socket] = $this->registeredConnection($server, $id);

        $this->dispatchClientMessage($server, $connection, [
            'type' => 'auth.join',
            'payload' => [
                'displayName' => $displayName,
            ],
        ]);

        return [$connection, $socket];
    }

    /**
     * @param array<string, mixed> $message
     */
    private function dispatchClientMessage(ChatServer $server, Connection $connection, array $message): void
    {
        $json = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $server->webSocketServer()->dispatcher()->dispatch(
            new MessageReceived($connection, Frame::text($json)),
        );
    }

    /**
     * @return array{0: Connection, 1: Socket}
     */
    private function registeredConnection(ChatServer $server, string $id): array
    {
        [$clientSocket, $peerSocket] = $this->connectedSocketPair();
        socket_set_nonblock($clientSocket);

        $connection = new Connection($id, $peerSocket, new FrameCodec());

        $server->webSocketServer()->connections()->add($connection);

        return [$connection, $clientSocket];
    }

    private function botMessageBody(Socket $socket): string
    {
        $envelope = $this->receiveMessageEnvelopeWithKind($socket, 'bot');
        $message = $envelope['payload']['message'] ?? null;

        if (!is_array($message)) {
            return '';
        }

        return (string) ($message['body'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function receiveMessageEnvelopeWithKind(Socket $socket, string $kind): array
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            foreach ($this->receiveAvailableEnvelopes($socket, 200000) as $envelope) {
                $message = $envelope['payload']['message'] ?? null;

                if (($envelope['type'] ?? null) === 'message.received' && is_array($message) && ($message['kind'] ?? null) === $kind) {
                    return $envelope;
                }
            }
        }

        throw new RuntimeException("Expected message.received envelope with kind {$kind} was not received.");
    }

    /**
     * @return array<string, mixed>
     */
    private function receiveServerEnvelope(Socket $socket, string $expectedType): array
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            foreach ($this->receiveAvailableEnvelopes($socket, 200000) as $envelope) {
                if (($envelope['type'] ?? null) === $expectedType) {
                    return $envelope;
                }
            }
        }

        throw new RuntimeException("Expected server envelope {$expectedType} was not received.");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function drainAvailableEnvelopes(Socket $socket): array
    {
        $envelopes = [];

        do {
            $batch = $this->receiveAvailableEnvelopes($socket, 0);
            $envelopes = [...$envelopes, ...$batch];
        } while ($batch !== []);

        return $envelopes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function receiveAvailableEnvelopes(Socket $socket, int $timeoutMicroseconds): array
    {
        $readSockets = [$socket];
        $writeSockets = null;
        $exceptSockets = null;
        $changed = socket_select($readSockets, $writeSockets, $exceptSockets, 0, $timeoutMicroseconds);

        if ($changed === false || $changed === 0) {
            return [];
        }

        $data = '';
        $bytes = socket_recv($socket, $data, 8192, 0);

        if ($bytes === false || $bytes === 0) {
            return [];
        }

        $codec = new FrameCodec();
        $envelopes = [];

        foreach ($codec->decodeAll($data, fromClient: false) as $frame) {
            $envelope = json_decode($frame->payload, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($envelope)) {
                $envelopes[] = $envelope;
            }
        }

        return $envelopes;
    }

    /**
     * @param list<array<string, mixed>> $envelopes
     */
    private function hasEnvelopeType(array $envelopes, string $type): bool
    {
        foreach ($envelopes as $envelope) {
            if (($envelope['type'] ?? null) === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $envelopes
     */
    private function hasBotMessage(array $envelopes): bool
    {
        foreach ($envelopes as $envelope) {
            $message = $envelope['payload']['message'] ?? null;

            if (($envelope['type'] ?? null) === 'message.received' && is_array($message) && ($message['kind'] ?? null) === 'bot') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: Socket, 1: Socket}
     */
    private function connectedSocketPair(): array
    {
        $serverSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $clientSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($serverSocket === false || $clientSocket === false) {
            throw new RuntimeException('Failed to create test sockets.');
        }

        $this->sockets[] = $serverSocket;
        $this->sockets[] = $clientSocket;

        socket_set_option($serverSocket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (!socket_bind($serverSocket, '127.0.0.1', 0)) {
            throw new RuntimeException('Failed to bind test server socket.');
        }

        if (!socket_listen($serverSocket, 1)) {
            throw new RuntimeException('Failed to listen on test server socket.');
        }

        $address = '';
        $port = 0;

        if (!socket_getsockname($serverSocket, $address, $port)) {
            throw new RuntimeException('Failed to read test server socket address.');
        }

        if (!socket_connect($clientSocket, $address, $port)) {
            throw new RuntimeException('Failed to connect test client socket.');
        }

        $peerSocket = socket_accept($serverSocket);

        if ($peerSocket === false) {
            throw new RuntimeException('Failed to accept test socket connection.');
        }

        $this->sockets[] = $peerSocket;

        return [$clientSocket, $peerSocket];
    }
}
