<?php

declare(strict_types=1);

namespace Micilini\PhpSockets\Tests\Unit\Chat;

use Micilini\PhpSockets\Chat\UsernameNormalizer;
use Micilini\PhpSockets\Exceptions\InvalidPayloadException;
use PHPUnit\Framework\TestCase;

final class UsernameNormalizerTest extends TestCase
{
    public function testDisplayNameIsTrimmedAndSpacesAreCollapsed(): void
    {
        $normalizer = new UsernameNormalizer();

        self::assertSame('Ana Paula', $normalizer->displayName('  Ana   Paula  '));
    }

    public function testKeyIsCaseInsensitive(): void
    {
        $normalizer = new UsernameNormalizer();

        self::assertSame('william', $normalizer->key('William'));
    }

    public function testEmptyDisplayNameIsRejected(): void
    {
        $normalizer = new UsernameNormalizer();

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Display name cannot be empty.');

        $normalizer->displayName('   ');
    }

    public function testTooLongDisplayNameIsRejected(): void
    {
        $normalizer = new UsernameNormalizer(maxLength: 5);

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Display name is too long.');

        $normalizer->displayName('William');
    }
}
