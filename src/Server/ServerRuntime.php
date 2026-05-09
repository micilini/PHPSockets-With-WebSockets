<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Server;

use Micilini\PhpSockets\Config\ServerConfig;
use Micilini\PhpSockets\Connection\Connection;
use Micilini\PhpSockets\Connection\ConnectionId;
use Micilini\PhpSockets\Connection\ConnectionRegistry;
use Micilini\PhpSockets\Contracts\ConnectionRegistryInterface;
use Micilini\PhpSockets\Contracts\EventDispatcherInterface;
use Micilini\PhpSockets\Events\ConnectionClosed;
use Micilini\PhpSockets\Events\ConnectionOpened;
use Micilini\PhpSockets\Events\MessageReceived;
use Micilini\PhpSockets\Events\MessageSent;
use Micilini\PhpSockets\Events\ServerError;
use Micilini\PhpSockets\Protocol\Frame;
use Micilini\PhpSockets\Protocol\FrameCodec;
use Micilini\PhpSockets\Protocol\Handshake;
use Micilini\PhpSockets\Protocol\Opcode;
use Socket;
use Throwable;

final class ServerRuntime
{
    private readonly SocketServer $socketServer;
    private readonly Loop $loop;
    private readonly FrameCodec $codec;

    public function __construct(
        private readonly ServerConfig $config,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ConnectionRegistryInterface $connections = new ConnectionRegistry(),
        ?SocketServer $socketServer = null,
        ?Loop $loop = null,
        ?FrameCodec $codec = null,
    ) {
        $this->socketServer = $socketServer ?? new SocketServer($this->config);
        $this->loop = $loop ?? new Loop();
        $this->codec = $codec ?? new FrameCodec($this->config->maxPayloadBytes);
    }

    public function connections(): ConnectionRegistryInterface
    {
        return $this->connections;
    }

    public function run(): void
    {
        $this->socketServer->start();
        $this->loop->start();

        while ($this->loop->isRunning()) {
            $this->tick();
            usleep($this->config->tickMicroseconds);
        }

        $this->stop();
    }

    public function stop(): void
    {
        $this->loop->stop();

        foreach ($this->connections->all() as $connection) {
            $connection->close();
            $this->connections->remove($connection->id());
        }

        $this->socketServer->close();
    }

    public function tick(): void
    {
        $readSockets = [$this->socketServer->socket()];

        foreach ($this->connections->all() as $connection) {
            $readSockets[] = $connection->socket();
        }

        $writeSockets = null;
        $exceptSockets = null;
        $changed = socket_select($readSockets, $writeSockets, $exceptSockets, 0, 0);

        if ($changed === false || $changed === 0) {
            return;
        }

        foreach ($readSockets as $socket) {
            if ($socket === $this->socketServer->socket()) {
                $this->acceptPendingConnection();
                continue;
            }

            $connection = $this->connections->findBySocketId(spl_object_id($socket));

            if ($connection instanceof Connection) {
                $this->readConnection($connection);
            }
        }
    }

    public function broadcast(string|Frame $message): int
    {
        $frame = is_string($message) ? Frame::text($message) : $message;
        $sent = 0;

        foreach ($this->connections->all() as $connection) {
            try {
                $connection->send($frame);
                $this->dispatcher->dispatch(new MessageSent($connection, $frame));
                $sent++;
            } catch (Throwable $exception) {
                $this->dispatcher->dispatch(new ServerError($exception, $connection));
                $this->closeConnection($connection);
            }
        }

        return $sent;
    }

    private function acceptPendingConnection(): void
    {
        $client = $this->socketServer->accept();

        if (!$client instanceof Socket) {
            return;
        }

        $request = '';
        $bytes = socket_recv($client, $request, 8192, 0);

        if ($bytes === false || $bytes === 0) {
            socket_close($client);
            return;
        }

        try {
            $response = Handshake::response($request);
            socket_write($client, $response);
            socket_set_nonblock($client);

            $connection = new Connection(
                ConnectionId::generate(),
                $client,
                $this->codec,
                $this->remoteAddress($client),
            );

            $this->connections->add($connection);
            $this->dispatcher->dispatch(new ConnectionOpened($connection));
        } catch (Throwable $exception) {
            $this->dispatcher->dispatch(new ServerError($exception));
            socket_close($client);
        }
    }

    private function readConnection(Connection $connection): void
    {
        $data = '';
        $bytes = socket_recv($connection->socket(), $data, $this->config->maxPayloadBytes + 16, 0);

        if ($bytes === false || $bytes === 0) {
            $this->closeConnection($connection);
            return;
        }

        try {
            $frames = $this->codec->decodeAll($data);

            foreach ($frames as $frame) {
                if (!$this->handleFrame($connection, $frame)) {
                    break;
                }
            }
        } catch (Throwable $exception) {
            $this->dispatcher->dispatch(new ServerError($exception, $connection));
            $this->closeConnection($connection);
        }
    }

    private function handleFrame(Connection $connection, Frame $frame): bool
    {
        if ($frame->opcode === Opcode::PING) {
            $connection->send(Frame::pong($frame->payload));

            return true;
        }

        if ($frame->opcode === Opcode::CLOSE) {
            $this->closeConnection($connection);

            return false;
        }

        $this->dispatcher->dispatch(new MessageReceived($connection, $frame));

        return true;
    }

    private function closeConnection(Connection $connection): void
    {
        $connection->close();
        $this->connections->remove($connection->id());
        $this->dispatcher->dispatch(new ConnectionClosed($connection));
    }

    private function remoteAddress(Socket $socket): ?string
    {
        $address = '';
        $port = 0;

        if (!socket_getpeername($socket, $address, $port)) {
            return null;
        }

        return $address . ':' . $port;
    }
}
