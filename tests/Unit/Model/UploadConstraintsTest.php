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

namespace Uhifadhi\Storage\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Storage\Model\EvidenceConstraints;
use Uhifadhi\Storage\Model\UploadConstraints;

/**
 * WHAT THE ZONE SAYS BEFORE ANYBODY DROPS ANYTHING.
 *
 * The design states the rule up front — "jpg · png · heic … up to 25 MB each ·
 * 10 files at a time" — and is explicit that it must never be discovered by
 * being refused. So the extensions on that line come from the SAME allowlist the
 * guard enforces, read through the same mime table that names a stored key, and
 * these assertions are what stops the two drifting into a promise the storage
 * does not keep.
 */
#[CoversClass(UploadConstraints::class)]
final class UploadConstraintsTest extends TestCase
{
    /**
     * THE ONE LIST BEHIND BOTH SENTENCES. The zone's kinds line and the refusal
     * a file gets are read from the SAME allowlist, so they cannot promise and
     * refuse different things — which is the whole reason a target states its
     * rule up front.
     *
     * @return iterable<string, array{list<string>, list<string>, string}>
     */
    public static function whatATargetTakes(): iterable
    {
        yield 'photographs only' => [['image/jpeg', 'image/png'], ['jpg', 'png'], 'a photograph'];
        yield 'documents only' => [['application/pdf'], ['pdf'], 'a document'];
        yield 'tracks only' => [
            ['application/gpx+xml', 'application/xml', 'text/xml'],
            // Bare XML is how a GPX ARRIVES, not a kind of its own: fileinfo has
            // never heard of GPX and reads the bytes as xml. So it earns no word
            // on the line and no noun in the sentence.
            ['gpx'],
            'a GPX track',
        ];
        yield 'photographs and documents' => [
            ['image/jpeg', 'application/pdf'],
            ['jpg', 'pdf'],
            'a photograph or document',
        ];
        yield 'all three' => [
            ['image/jpeg', 'application/pdf', 'application/gpx+xml', 'text/xml'],
            ['jpg', 'pdf', 'gpx'],
            'a photograph, document or GPX track',
        ];
    }

    /**
     * @param list<string> $mimeTypes
     * @param list<string> $line
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('whatATargetTakes')]
    public function testTheKindsLineAndTheRefusalComeFromOneList(array $mimeTypes, array $line, string $noun): void
    {
        $constraints = new UploadConstraints($mimeTypes, 1000);

        self::assertSame($line, $constraints->extensions());
        // A file of a kind this target does not take at all — named in the
        // target's own words rather than in the file's.
        self::assertSame($noun, $constraints->refusalNounFor('application/x-msdownload'));
    }

    /**
     * THE RIGHT KIND, THE WRONG SPELLING. A target that takes PNGs and not
     * JPEGs cannot be refused with "that is not a photograph" — it is one. Where
     * the refusal is inside a kind the sentence names the spellings instead, and
     * they are the same spellings the zone's line printed.
     */
    public function testARefusalInsideOneKindNamesTheSpellingsRatherThanTheKind(): void
    {
        $constraints = new UploadConstraints(['image/png', 'image/webp'], 1000);

        self::assertSame('one of png · webp', $constraints->refusalNounFor('image/jpeg'));
    }

    /** A type nothing could detect is refused against the whole list, in kinds. */
    public function testAnUndetectableTypeIsRefusedAgainstTheWholeList(): void
    {
        self::assertSame(
            'a photograph',
            new UploadConstraints(['image/png'], 1000)->refusalNounFor(null),
        );
    }

    public function testItTakesTheDeploymentsOwnAllowlistAndCap(): void
    {
        $constraints = UploadConstraints::from(EvidenceConstraints::default());

        self::assertSame(EvidenceConstraints::DEFAULT_MIME_TYPES, $constraints->allowedMimeTypes);
        self::assertSame(EvidenceConstraints::DEFAULT_MAX_BYTES, $constraints->maxBytes);
        self::assertSame(UploadConstraints::DEFAULT_MAX_FILES, $constraints->maxFiles);
    }

    public function testTheKindsLineNamesExtensionsNotMimeTypes(): void
    {
        $constraints = new UploadConstraints(['image/jpeg', 'image/png', 'application/pdf'], 1000, 4);

        self::assertSame(['jpg', 'png', 'pdf'], $constraints->extensions());
    }

    public function testAnExtensionIsNamedOnceHoweverManyTypesSpellIt(): void
    {
        $constraints = new UploadConstraints(['image/jpeg', 'image/jpeg'], 1000, 1);

        self::assertSame(['jpg'], $constraints->extensions());
    }

    public function testATypeNothingCanNameIsLeftOffTheLineRatherThanGuessedAt(): void
    {
        $constraints = new UploadConstraints(['image/png', 'application/x-not-a-real-type'], 1000, 1);

        self::assertSame(['png'], $constraints->extensions());
    }

    /**
     * The picker is handed the MIME TYPES themselves — the carriers included,
     * because the operating system's dialog filters on what a file will actually
     * be read as, and a GPX is read as xml.
     */
    public function testTheFilePickerIsHandedEveryMimeTypeIncludingTheCarriers(): void
    {
        $constraints = new UploadConstraints(['image/jpeg', 'application/gpx+xml', 'text/xml'], 1000, 1);

        self::assertSame('image/jpeg,application/gpx+xml,text/xml', $constraints->accept());
    }

    public function testItRefusesAnAllowlistThatWouldAcceptNothing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UploadConstraints([], 1000, 1);
    }

    public function testItRefusesAFileCountThatWouldAcceptNothing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UploadConstraints(['image/png'], 1000, 0);
    }

    public function testATargetMayNarrowTheDeploymentsRulesButTheGuardStillHoldsBoth(): void
    {
        $constraints = new UploadConstraints(['image/png'], 500, 2);

        self::assertTrue($constraints->allows('image/png'));
        self::assertFalse($constraints->allows('image/jpeg'));
        self::assertTrue($constraints->fits(500));
        self::assertFalse($constraints->fits(501));
    }

    /**
     * An UNDETECTABLE type passes, exactly as {@see EvidenceConstraints::allows()}
     * lets it pass: the two guards must agree, or the component would draw a
     * refusal the storage would then not make.
     */
    public function testAnUndetectableTypeIsAllowedBecauseTheStorageAllowsIt(): void
    {
        self::assertTrue(new UploadConstraints(['image/png'], 500, 2)->allows(null));
    }
}
