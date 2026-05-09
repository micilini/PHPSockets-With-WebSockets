<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Integration\Chat;

use Micilini\PhpSockets\Chat\ChatKernel;
use Micilini\PhpSockets\Chat\ChatMessage;
use Micilini\PhpSockets\Chat\ChatServer;
use Micilini\PhpSockets\Config\ChatConfig;
use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\Connection\Connection;
use Micilini\PhpSockets\Events\MessageReceived;
use Micilini\PhpSockets\Protocol\Frame;
use Micilini\PhpSockets\Protocol\FrameCodec;
use Micilini\PhpSockets\Server\WebSocketServer;
use Micilini\PhpSockets\Storage\File\FileAttachmentStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Socket;

final class FileMessageTest extends TestCase
{
    /**
     * @var list<Socket>
     */
    private array $sockets = [];

    private ?string $attachmentDirectory = null;

    protected function tearDown(): void
    {
        foreach ($this->sockets as $socket) {
            socket_close($socket);
        }

        $this->sockets = [];
        $this->removeAttachmentDirectory();
    }

    public function testGlobalFileMessageCreatesFileMessageWithoutEchoingBase64Content(): void
    {
        $server = $this->server();
        [$connection, $socket] = $this->authenticatedConnection($server, 'conn_william', 'William');

        $this->drainAvailableEnvelopes($socket);

        $this->dispatchClientMessage($server, $connection, [
            'type' => 'message.file',
            'payload' => [
                'scope' => 'global',
                'clientMessageId' => 'client_file_123',
                'caption' => 'Attached hello',
                'attachment' => [
                    'fileName' => 'hello.txt',
                    'mimeType' => 'text/plain',
                    'sizeBytes' => 5,
                    'contentBase64' => base64_encode('hello'),
                ],
            ],
        ]);

        $envelope = $this->receiveServerEnvelope($socket, 'message.received');
        $message = $envelope['payload']['message'] ?? null;

        self::assertIsArray($message);
        self::assertSame('file', $message['kind'] ?? null);
        self::assertSame('client_file_123', $message['metadata']['clientMessageId'] ?? null);
        self::assertSame('hello.txt', $message['body']['fileName'] ?? null);
        self::assertSame('text/plain', $message['body']['mimeType'] ?? null);
        self::assertSame(5, $message['body']['sizeBytes'] ?? null);
        self::assertSame('Attached hello', $message['body']['caption'] ?? null);
        self::assertIsString($message['body']['downloadDataUrl'] ?? null);
        self::assertArrayNotHasKey('contentBase64', $message['body']);

        $stored = $server->kernel()->messageStore()->messagesForRoom('global');

        self::assertCount(1, $stored);
        self::assertInstanceOf(ChatMessage::class, $stored[0]);
        self::assertSame('file', $stored[0]->kind);
        self::assertSame('client_file_123', $stored[0]->metadata['clientMessageId'] ?? null);
    }

    public function testOversizedFileMessageIsRejectedWithoutGenericError(): void
    {
        $server = $this->server(ChatConfig::new(maxAttachmentBytes: 4));
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

        $envelope = $this->receiveServerEnvelope($socket, 'attachment.rejected');

        self::assertSame('Attachment exceeds the maximum allowed size.', $envelope['payload']['message'] ?? null);
    }

    public function testPdfFileMessageIsAcceptedWithDownloadDataUrl(): void
    {
        $server = $this->server();
        [$connection, $socket] = $this->authenticatedConnection($server, 'conn_william', 'William');

        $this->drainAvailableEnvelopes($socket);

        $content = "%PDF-1.4\nsmall pdf\n";

        $this->dispatchClientMessage($server, $connection, [
            'type' => 'message.file',
            'payload' => [
                'scope' => 'global',
                'attachment' => [
                    'fileName' => 'sample.pdf',
                    'mimeType' => 'application/pdf',
                    'sizeBytes' => strlen($content),
                    'contentBase64' => base64_encode($content),
                ],
            ],
        ]);

        $envelope = $this->receiveServerEnvelope($socket, 'message.received');
        $message = $envelope['payload']['message'] ?? null;

        self::assertIsArray($message);
        self::assertSame('application/pdf', $message['body']['mimeType'] ?? null);
        self::assertNull($message['body']['previewDataUrl'] ?? null);
        self::assertStringStartsWith('data:application/pdf;base64,', (string) ($message['body']['downloadDataUrl'] ?? ''));
        self::assertArrayNotHasKey('contentBase64', $message['body']);
    }

    private function server(?ChatConfig $config = null): ChatServer
    {
        $this->attachmentDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpsockets-file-message-test-' . bin2hex(random_bytes(6));

        return new ChatServer(
            server: new WebSocketServer(ServerConfig::new()),
            kernel: new ChatKernel(
                config: $config ?? ChatConfig::new(),
                attachmentStore: new FileAttachmentStore($this->attachmentDirectory),
            ),
        );
    }

    private function removeAttachmentDirectory(): void
    {
        if ($this->attachmentDirectory === null || !is_dir($this->attachmentDirectory)) {
            return;
        }

        foreach (glob($this->attachmentDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($this->attachmentDirectory);
        $this->attachmentDirectory = null;
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
