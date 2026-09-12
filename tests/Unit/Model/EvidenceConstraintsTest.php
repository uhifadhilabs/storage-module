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

    public function testTheDefaultAllowlistIsOneOfEachKindTheHubNames(): void
    {
        $constraints = EvidenceConstraints::default();

        // The five a camera may send — patrol's original ALLOWED_MIME_TYPES, in
        // the same order — then the signed document a money case carries, then
        // the track, under the three types a GPX can arrive as.
        self::assertSame(
            [
                'image/jpeg', 'image/png', 'image/heic', 'image/heif', 'image/webp',
                'application/pdf',
                'application/gpx+xml', 'application/xml', 'text/xml',
            ],
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
     * A SIZE CODE IS NOT A BROKEN TRANSFER. PHP truncates an upload that is over
     * its own ceiling and reports it through the same isValid() as a failed one,
     * so reading every code alike told a person their upload had been damaged
     * when in fact the server had a limit nobody had mentioned. The limit that
     * did the refusing is the one in the sentence.
     */
    public function testItNamesTheServersOwnLimitWhenPhpCutTheUploadShort(): void
    {
        $truncated = new UploadedFile(
            self::IMAGES.'/landscape-800x600.jpg',
            'photo.jpg',
            'image/jpeg',
            \UPLOAD_ERR_INI_SIZE,
            test: true,
        );

        try {
            EvidenceConstraints::default()->validate($truncated);
            self::fail('An upload PHP had already truncated was accepted.');
        } catch (EvidenceRejectedException $exception) {
            self::assertSame(RejectionReasonEnum::ExceedsServerLimit, $exception->reason);
            self::assertSame((int) min(UploadedFile::getMaxFilesize(), \PHP_INT_MAX), $exception->details['serverMaxBytes'] ?? null);
            self::assertStringContainsString('larger than this server accepts', $exception->getMessage());
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
     * THE DEFAULT COVERS ALL THREE OF THE HUB'S KINDS, not only the first.
     *
     * The Files hub's own filter row reads "Photos / Documents / Tracks", and a
     * deployment that has configured nothing must be able to receive one of
     * each — otherwise the second and third chips can only ever be empty, and
     * a module whose target genuinely takes a GPX is refused by a default
     * nobody chose. A deployment may still NARROW it.
     */
    public function testTheDefaultAllowlistCoversPhotographsDocumentsAndTracks(): void
    {
        $default = EvidenceConstraints::default();

        self::assertTrue($default->allows('image/jpeg'), 'photographs');
        self::assertTrue($default->allows('application/pdf'), 'documents');
        self::assertTrue($default->allows('application/gpx+xml'), 'tracks');
    }

    /**
     * A GPX ARRIVES AS XML AND MUST STILL BE ACCEPTED. fileinfo reads the BYTES
     * and has never heard of GPX, so a real track file detects as `text/xml`
     * (or `application/xml`). Those two are on the list for that reason alone —
     * they are how a track arrives, not a kind of their own.
     */
    public function testATrackFileIsAcceptedUnderTheTypeItsBytesActuallyDetectAs(): void
    {
        $default = EvidenceConstraints::default();
        $gpx = new File(\dirname(__DIR__, 2).'/Fixtures/tracks/walk.gpx');

        $detected = $default->detect($gpx);

        self::assertContains($detected, ['text/xml', 'application/xml'], 'fileinfo reads the bytes, and they are xml');
        self::assertTrue($default->allows($detected), 'a track the deployment configured nothing about is accepted');

        // And the guard agrees with the predicate: it throws on a refusal, so
        // reaching the line after it is the rest of the assertion.
        $default->validate($gpx);
    }

    /**
     * WHAT THIS DEPLOYMENT ACCEPTS, IN WORDS — read from the very list the guard
     * enforces, so a refusal cannot name something the allowlist does not say.
     */
    public function testItSaysWhatItAcceptsInTheHubsOwnWords(): void
    {
        self::assertSame('a photograph, document or GPX track', EvidenceConstraints::default()->describe());
        self::assertSame('a photograph', new EvidenceConstraints(['image/png'], 1000)->describe());
        self::assertSame('a document', new EvidenceConstraints(['application/pdf'], 1000)->describe());
    }

    /** The refusal says what IS accepted, not what a photograph is. */
    public function testTheRefusalNamesWhatThisDeploymentAccepts(): void
    {
        $constraints = new EvidenceConstraints(['application/gpx+xml', 'application/xml', 'text/xml'], 1_000_000);

        try {
            $constraints->validate(new File(self::IMAGES.'/landscape-800x600.jpg'));
            self::fail('A photograph was accepted by a track-only deployment.');
        } catch (EvidenceRejectedException $exception) {
            self::assertSame(RejectionReasonEnum::UnsupportedType, $exception->reason);
            self::assertSame('That file is not a GPX track.', $exception->getMessage());
        }
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
