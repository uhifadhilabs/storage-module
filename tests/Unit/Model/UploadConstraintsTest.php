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

    public function testTheFilePickerIsHandedTheMimeTypesThemselves(): void
    {
        $constraints = new UploadConstraints(['image/jpeg', 'application/pdf'], 1000, 1);

        self::assertSame('image/jpeg,application/pdf', $constraints->accept());
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
