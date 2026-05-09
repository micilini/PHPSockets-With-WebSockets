<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Contracts;

interface PresenceStoreInterface
{
    public function isOnline(string $userId): bool;

    /**
     * @return list<string>
     */
    public function onlineUserIds(): array;
}
