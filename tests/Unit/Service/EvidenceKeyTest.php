<?php

declare(strict_types=1);

/*
 * This file is part of the UhifadhiLabs Storage Module.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Storage\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Storage\Exception\InvalidEvidenceKeyException;
use Uhifadhi\Storage\Service\EvidenceKey;

/**
 * Key discipline. A key is a RELATIVE path inside one storage and nothing else:
 * it is what modules persist in their own tables, what the serving route
 * carries in a URL, and what a voter reads a prefix from. If a key could ever
 * be absolute, escape upwards, or carry a Windows drive, all three of those
 * break at once — so every rule is pinned here.
 */
final class EvidenceKeyTest extends TestCase
{
    public function testItJoinsAPrefixAndAClientKeyWithTheDetectedExtension(): void
    {
        self::assertSame(
            'observation/0199a/abc-123.jpg',
            EvidenceKey::build('observation/0199a', 'abc-123', 'jpg'),
        );
    }

    public function testItToleratesASlashHeavyPrefixWithoutProducingEmptySegments(): void
    {
        // Callers assemble prefixes by concatenation; a stray slash is a typo,
        // not an attack, and must not turn into "a//b".
        self::assertSame('a/b/k.png', EvidenceKey::build('/a/b/', 'k', 'png'));
    }

    /**
     * The thumbnail lives BESIDE the original under a derived key, so a module
     * that stored the original can always name the variant without storing a
     * second column it might forget to migrate.
     */
    public function testTheThumbnailKeyIsDerivedFromTheOriginal(): void
    {
        self::assertSame('observation/x/k.jpg.thumb.jpg', EvidenceKey::thumb('observation/x/k.jpg'));
    }

    /** @return iterable<string, array{string}> */
    public static function refusedKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'absolute' => ['/etc/passwd'];
        yield 'parent traversal' => ['observation/../../etc/passwd'];
        yield 'bare parent segment' => ['..'];
        yield 'current-dir segment' => ['a/./b'];
        yield 'double slash' => ['a//b'];
        yield 'trailing slash' => ['a/b/'];
        yield 'backslash' => ['a\\b'];
        yield 'null byte' => ["a/b\0.jpg"];
        yield 'windows drive' => ['C:/windows/system32'];
        yield 'newline' => ["a/b\n.jpg"];
        yield 'space' => ['a/ b.jpg'];
    }

    #[DataProvider('refusedKeys')]
    public function testItRefusesAnythingThatIsNotARelativeKey(string $key): void
    {
        self::assertFalse(EvidenceKey::isValid($key));

        $this->expectException(InvalidEvidenceKeyException::class);
        EvidenceKey::assertValid($key);
    }

    /** @return iterable<string, array{string}> */
    public static function acceptedKeys(): iterable
    {
        yield 'single segment' => ['photo.jpg'];
        yield 'nested' => ['observation/0199a-bcd/ef12.jpg'];
        yield 'thumb variant' => ['observation/x/ef12.jpg.thumb.jpg'];
        yield 'underscores and dots' => ['a_b/c.d-e.png'];
    }

    #[DataProvider('acceptedKeys')]
    public function testItAcceptsOrdinaryRelativeKeys(string $key): void
    {
        self::assertTrue(EvidenceKey::isValid($key));

        // Must not throw.
        EvidenceKey::assertValid($key);
    }

    /**
     * The client half of a key is attacker-controlled text. It is never allowed
     * to introduce structure — one bad clientKey would otherwise let a caller
     * write outside its own prefix and, worse, outside its own voter's reach.
     */
    public function testAClientKeyMayNotSmuggleStructure(): void
    {
        $this->expectException(InvalidEvidenceKeyException::class);

        EvidenceKey::build('observation/x', '../../../escape', 'jpg');
    }

    public function testAPrefixMayNotTraverseUpwardsEither(): void
    {
        $this->expectException(InvalidEvidenceKeyException::class);

        EvidenceKey::build('observation/../..', 'k', 'jpg');
    }

    /**
     * The prefix is what a voter matches on, so it is READ BACK from a stored
     * key rather than remembered separately.
     */
    public function testTheFirstSegmentIsReadableAsTheOwningPrefix(): void
    {
        self::assertSame('observation', EvidenceKey::rootSegment('observation/0199a/k.jpg'));
        self::assertSame('photo.jpg', EvidenceKey::rootSegment('photo.jpg'));
    }
}
