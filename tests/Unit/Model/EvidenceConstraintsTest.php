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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Uhifadhi\Storage\Enum\RejectionReasonEnum;
use Uhifadhi\Storage\Exception\EvidenceRejectedException;
use Uhifadhi\Storage\Model\EvidenceConstraints;

/**
 * The guard patrol-module already applies, lifted out so every module applies
 * the SAME one. These assertions are deliberately the semantics of
 * PhotoSyncService::guardFile() — patrol must be able to adopt this class and
 * reject exactly what it rejected before, no more and no less.
 */
final class EvidenceConstraintsTest extends TestCase
{
    private const string IMAGES = __DIR__.'/../../Fixtures/images';

    public function testTheDefaultAllowlistIsWhatACameraMaySend(): void
    {
        $constraints = EvidenceConstraints::default();

        // Same five as patrol's ALLOWED_MIME_TYPES, in the same spirit:
        // anything else is not a photograph.
        self::assertSame(
            ['image/jpeg', 'image/png', 'image/heic', 'image/heif', 'image/webp'],
            $constraints->allowedMimeTypes,
        );
        self::assertSame(12 * 1024 * 1024, $constraints->maxBytes);
    }

    public function testItAcceptsAnOrdinaryPhotograph(): void
    {
        EvidenceConstraints::default()->validate(new File(self::IMAGES.'/landscape-800x600.jpg'));

        $this->expectNotToPerformAssertions();
    }

    public function testItRefusesAFileThatIsNotAPhotograph(): void
    {
        try {
            EvidenceConstraints::default()->validate(new File(self::IMAGES.'/not-an-image.php'));
            self::fail('A PHP script was accepted as evidence.');
        } catch (EvidenceRejectedException $exception) {
            self::assertSame(RejectionReasonEnum::UnsupportedType, $exception->reason);
            // The DETECTED type is reported, never the claimed one.
            self::assertSame('text/x-php', $exception->details['mimeType'] ?? null);
        }
    }

    public function testItRefusesAFileLargerThanTheDeploymentAccepts(): void
    {
        $constraints = new EvidenceConstraints(EvidenceConstraints::DEFAULT_MIME_TYPES, maxBytes: 128);

        try {
            $constraints->validate(new File(self::IMAGES.'/landscape-800x600.jpg'));
            self::fail('An oversized photo was accepted.');
        } catch (EvidenceRejectedException $exception) {
            self::assertSame(RejectionReasonEnum::TooLarge, $exception->reason);
            self::assertSame(128, $exception->details['maxBytes'] ?? null);
            self::assertIsInt($exception->details['byteSize'] ?? null);
        }
    }

    /**
     * An upload that did not arrive intact is refused before anything else is
     * asked of it — mirroring patrol, which checks isValid() first.
     */
    public function testItRefusesAnUploadThatDidNotArriveIntact(): void
    {
        $broken = new UploadedFile(
            self::IMAGES.'/landscape-800x600.jpg',
            'photo.jpg',
            'image/jpeg',
            \UPLOAD_ERR_PARTIAL,
            test: true,
        );

        try {
            EvidenceConstraints::default()->validate($broken);
            self::fail('A truncated upload was accepted.');
        } catch (EvidenceRejectedException $exception) {
            self::assertSame(RejectionReasonEnum::UploadIncomplete, $exception->reason);
        }
    }

    /**
     * The type is read from the BYTES, so a lying Content-Type buys nothing.
     * patrol's guard has always worked this way; this pins that it still does.
     */
    public function testAClientDeclaredTypeCannotLaunderAScript(): void
    {
        $liar = new UploadedFile(
            self::IMAGES.'/not-an-image.php',
            'holiday.jpg',
            'image/jpeg', // the client says photograph; the bytes say PHP
            test: true,
        );

        $this->expectException(EvidenceRejectedException::class);

        EvidenceConstraints::default()->validate($liar);
    }

    /**
     * Patrol's guard reads: `if (null !== $mimeType && !in_array(...))`. A file
     * whose type cannot be determined at all is NOT rejected there, so it is
     * not rejected here either — adopting this class must not change what
     * patrol accepts. The residual risk is carried elsewhere and deliberately:
     * such a file lands in a private storage outside the document root and is
     * only ever served back with a fixed Content-Type and `nosniff`.
     */
    public function testAnUndetectableTypeIsNotRejectedByTheGuardItself(): void
    {
        $constraints = EvidenceConstraints::default();

        self::assertTrue($constraints->allows(null));
        self::assertFalse($constraints->allows('text/x-php'));
        self::assertTrue($constraints->allows('image/heic'));
    }

    public function testADeploymentMayNarrowTheAllowlist(): void
    {
        $jpegOnly = new EvidenceConstraints(['image/jpeg'], 1024 * 1024);

        self::assertTrue($jpegOnly->allows('image/jpeg'));
        self::assertFalse($jpegOnly->allows('image/png'));
    }

    public function testTheExtensionComesFromTheDetectedTypeNeverTheFilename(): void
    {
        // An attacker-controlled filename is how an upload directory ends up
        // holding a ".php". These five are the default allowlist, pinned
        // because the extension of a stored key is permanent: a change in what
        // symfony/mime answers first must fail the build rather than silently
        // rename tomorrow's evidence.
        self::assertSame('png', EvidenceConstraints::extensionFor('image/png'));
        self::assertSame('heic', EvidenceConstraints::extensionFor('image/heic'));
        self::assertSame('heif', EvidenceConstraints::extensionFor('image/heif'));
        self::assertSame('webp', EvidenceConstraints::extensionFor('image/webp'));
        self::assertSame('jpg', EvidenceConstraints::extensionFor('image/jpeg'));
    }

    /**
     * A deployment that widens the allowlist to the signed document a money
     * case carries must get a ".pdf": the extension names what the bytes are,
     * for every allowed type and not only the images.
     */
    public function testAWidenedTypeIsKeyedByItsOwnExtension(): void
    {
        self::assertSame('pdf', EvidenceConstraints::extensionFor('application/pdf'));
        self::assertSame('gpx', EvidenceConstraints::extensionFor('application/gpx+xml'));
    }

    /**
     * A type nothing can name an extension for is refused outright, so no file
     * is ever stored under an extension that misdescribes it.
     */
    public function testATypeNothingCanNameIsRefusedRatherThanGuessed(): void
    {
        try {
            EvidenceConstraints::extensionFor('application/x-uhifadhi-nonesuch');
            self::fail('An unnameable type was given an extension anyway.');
        } catch (EvidenceRejectedException $exception) {
            self::assertSame(RejectionReasonEnum::UnsupportedType, $exception->reason);
            self::assertSame('application/x-uhifadhi-nonesuch', $exception->details['mimeType'] ?? null);
        }
    }

    /**
     * An undetectable type is still accepted by the guard, so it still needs a
     * key: it takes the extension of the type it is recorded as,
     * application/octet-stream, rather than a claim to be a photograph.
     */
    public function testAnUndetectableTypeIsKeyedAsOpaqueBytes(): void
    {
        self::assertSame('bin', EvidenceConstraints::extensionFor(null));
    }
}
