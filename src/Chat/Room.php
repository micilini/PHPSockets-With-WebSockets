<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Chat;

use DateTimeImmutable;

final readonly class Room
{
    public const TYPE_GLOBAL = 'global';
    public const TYPE_DIRECT = 'direct';
    public const TYPE_PRIVATE_GROUP = 'private_group';

    /**
     * @param list<string> $memberUserIds
     */
    public function __construct(
        public string $id,
        public string $type,
        public ?string $name,
        public string $createdBy,
        public array $memberUserIds,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public static function global(): self
    {
        return new self(
            id: 'global',
            type: self::TYPE_GLOBAL,
            name: 'Global',
            createdBy: 'system',
            memberUserIds: [],
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param list<string> $memberUserIds
     */
    public static function direct(string $id, array $memberUserIds, string $createdBy): self
    {
        return new self(
            id: $id,
            type: self::TYPE_DIRECT,
            name: null,
            createdBy: $createdBy,
            memberUserIds: $memberUserIds,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param list<string> $memberUserIds
     */
    public static function privateGroup(string $name, string $createdBy, array $memberUserIds): self
    {
        return new self(
            id: 'room_' . bin2hex(random_bytes(16)),
            type: self::TYPE_PRIVATE_GROUP,
            name: $name !== '' ? $name : null,
            createdBy: $createdBy,
            memberUserIds: $memberUserIds,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function hasMember(string $userId): bool
    {
        if ($this->type === self::TYPE_GLOBAL) {
            return true;
        }

        return in_array($userId, $this->memberUserIds, true);
    }

    public function withMember(string $userId): self
    {
        if ($this->hasMember($userId) && $this->type !== self::TYPE_GLOBAL) {
            return $this;
        }

        $memberUserIds = $this->memberUserIds;

        if (!in_array($userId, $memberUserIds, true)) {
            $memberUserIds[] = $userId;
        }

        return new self(
            id: $this->id,
            type: $this->type,
            name: $this->name,
            createdBy: $this->createdBy,
            memberUserIds: $memberUserIds,
            createdAt: $this->createdAt,
        );
    }

    public function withoutMember(string $userId): self
    {
        return new self(
            id: $this->id,
            type: $this->type,
            name: $this->name,
            createdBy: $this->createdBy,
            memberUserIds: array_values(array_filter(
                $this->memberUserIds,
                static fn (string $memberUserId): bool => $memberUserId !== $userId,
            )),
            createdAt: $this->createdAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'createdBy' => $this->createdBy,
            'memberUserIds' => $this->memberUserIds,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
