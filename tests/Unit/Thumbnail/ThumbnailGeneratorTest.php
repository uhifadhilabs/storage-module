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

namespace Uhifadhi\Storage\Tests\Unit\Thumbnail;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Storage\Thumbnail\GdThumbnailer;
use Uhifadhi\Storage\Thumbnail\ImagickThumbnailer;
use Uhifadhi\Storage\Thumbnail\ThumbnailerInterface;
use Uhifadhi\Storage\Thumbnail\ThumbnailGenerator;

/**
 * Thumbnails are a CONVENIENCE, and the whole design follows from that: a
 * thumbnail that cannot be made is a null, never an exception, because losing
 * a ranger's photograph to a missing image library would be an absurd trade.
 */
final class ThumbnailGeneratorTest extends TestCase
{
    private const string IMAGES = __DIR__.'/../../Fixtures/images';

    /** @return array{int, int} */
    private function sizeOf(string $bytes): array
    {
        $info = getimagesizefromstring($bytes);
        self::assertIsArray($info, 'The generated thumbnail is not a readable image.');

        return [$info[0], $info[1]];
    }

    private function generator(int $longEdge = 400): ThumbnailGenerator
    {
        return new ThumbnailGenerator([new ImagickThumbnailer(), new GdThumbnailer()], $longEdge);
    }

    public function testALandscapePhotoIsScaledToFourHundredOnItsLongEdge(): void
    {
        $bytes = $this->generator()->generate(self::IMAGES.'/landscape-800x600.jpg', 'image/jpeg');

        self::assertIsString($bytes);
        self::assertSame([400, 300], $this->sizeOf($bytes));
    }

    public function testAPortraitPhotoIsScaledOnItsHeightInstead(): void
    {
        // 300x900 -> the LONG edge is the height, so that is what becomes 400.
        $bytes = $this->generator()->generate(self::IMAGES.'/portrait-300x900.png', 'image/png');

        self::assertIsString($bytes);
        self::assertSame([133, 400], $this->sizeOf($bytes));
    }

    /** Whatever came in, ONE JPEG comes out — the variant is a preview, not a copy. */
    public function testTheVariantIsAlwaysJpegWhateverTheSourceWas(): void
    {
        $bytes = $this->generator()->generate(self::IMAGES.'/portrait-300x900.png', 'image/png');

        self::assertIsString($bytes);
        self::assertSame('image/jpeg', getimagesizefromstring($bytes)['mime'] ?? null);
    }

    public function testAWebpSourceIsAccepted(): void
    {
        $bytes = $this->generator()->generate(self::IMAGES.'/wide-500x250.webp', 'image/webp');

        self::assertIsString($bytes);
        self::assertSame([400, 200], $this->sizeOf($bytes));
    }

    /**
     * A photo already smaller than the target is NOT blown up. Upscaling costs
     * bytes and adds nothing a viewer can see.
     */
    public function testAnAlreadySmallPhotoIsNotUpscaled(): void
    {
        $bytes = $this->generator()->generate(self::IMAGES.'/tiny-100x80.jpg', 'image/jpeg');

        self::assertIsString($bytes);
        self::assertSame([100, 80], $this->sizeOf($bytes));
    }

    /**
     * THE HONEST NULL. HEIC is what an iPhone sends by default, and neither GD
     * nor an ImageMagick built without libheif can decode it. When that is the
     * situation, say so — do not fail, and do not write a broken thumbnail.
     */
    public function testAnUndecodableSourceYieldsNullRatherThanAnError(): void
    {
        $generator = new ThumbnailGenerator([new GdThumbnailer()], 400);

        // GD has no HEIC decoder in any build, so this is null on every machine.
        self::assertNull($generator->generate(self::IMAGES.'/photo.heic', 'image/heic'));
    }

    public function testAGeneratorWithNoUsableEngineAtAllSimplyReturnsNull(): void
    {
        $generator = new ThumbnailGenerator([], 400);

        self::assertNull($generator->generate(self::IMAGES.'/landscape-800x600.jpg', 'image/jpeg'));
    }

    /** Corrupt bytes are a null too: a thumbnail never decides an upload's fate. */
    public function testBytesThatAreNotAnImageYieldNull(): void
    {
        self::assertNull($this->generator()->generate(self::IMAGES.'/not-an-image.php', 'image/jpeg'));
    }

    public function testAMissingFileYieldsNull(): void
    {
        self::assertNull($this->generator()->generate(self::IMAGES.'/nothing-here.jpg', 'image/jpeg'));
    }

    /**
     * Imagick first, GD second — Imagick reads more formats (HEIC among them
     * where libheif is present) and resamples better.
     */
    public function testImagickIsPreferredOverGdWhenBothCanDecode(): void
    {
        $imagick = $this->engine('image/jpeg', 'IMAGICK-BYTES', available: true);
        $gd = $this->engine('image/jpeg', 'GD-BYTES', available: true);

        $generator = new ThumbnailGenerator([$imagick, $gd], 400);

        self::assertSame('IMAGICK-BYTES', $generator->generate('/any/path.jpg', 'image/jpeg'));
    }

    public function testItFallsThroughToTheNextEngineWhenThePreferredOneIsUnavailable(): void
    {
        $imagick = $this->engine('image/jpeg', 'IMAGICK-BYTES', available: false);
        $gd = $this->engine('image/jpeg', 'GD-BYTES', available: true);

        $generator = new ThumbnailGenerator([$imagick, $gd], 400);

        self::assertSame('GD-BYTES', $generator->generate('/any/path.jpg', 'image/jpeg'));
    }

    public function testItFallsThroughToTheNextEngineWhenThePreferredOneCannotDecodeTheFormat(): void
    {
        $imagick = $this->engine('image/heic', 'IMAGICK-BYTES', available: true);
        $gd = $this->engine('image/jpeg', 'GD-BYTES', available: true);

        $generator = new ThumbnailGenerator([$imagick, $gd], 400);

        self::assertSame('GD-BYTES', $generator->generate('/any/path.jpg', 'image/jpeg'));
    }

    /** An engine that is present and claims the format but still fails hands on. */
    public function testItFallsThroughWhenThePreferredEngineFailsAtDecodeTime(): void
    {
        $imagick = $this->engine('image/jpeg', null, available: true);
        $gd = $this->engine('image/jpeg', 'GD-BYTES', available: true);

        $generator = new ThumbnailGenerator([$imagick, $gd], 400);

        self::assertSame('GD-BYTES', $generator->generate('/any/path.jpg', 'image/jpeg'));
    }

    #[RequiresPhpExtension('gd')]
    public function testGdReportsTheFormatsItsBuildActuallyHas(): void
    {
        $gd = new GdThumbnailer();

        self::assertTrue($gd->isAvailable());
        self::assertTrue($gd->canDecode('image/jpeg'));
        self::assertTrue($gd->canDecode('image/png'));
        // No GD build in existence decodes HEIC — this is not build-dependent.
        self::assertFalse($gd->canDecode('image/heic'));
        self::assertFalse($gd->canDecode('image/heif'));
    }

    /**
     * Imagick's answer depends on how the SYSTEM ImageMagick was compiled, so
     * this asks the library rather than asserting a fixed list.
     */
    public function testImagickAnswersFromItsOwnFormatRegistry(): void
    {
        $imagick = new ImagickThumbnailer();

        self::assertSame(class_exists(\Imagick::class), $imagick->isAvailable());

        if (!$imagick->isAvailable()) {
            self::assertFalse($imagick->canDecode('image/jpeg'), 'An absent library can decode nothing.');

            return;
        }

        self::assertTrue($imagick->canDecode('image/jpeg'));
        self::assertFalse($imagick->canDecode('application/pdf'), 'PDF is not evidence and is never rasterised.');
    }

    private function engine(string $decodes, ?string $result, bool $available): ThumbnailerInterface
    {
        return new class($decodes, $result, $available) implements ThumbnailerInterface {
            public function __construct(
                private readonly string $decodes,
                private readonly ?string $result,
                private readonly bool $available,
            ) {
            }

            public function isAvailable(): bool
            {
                return $this->available;
            }

            public function canDecode(string $mimeType): bool
            {
                return $this->decodes === $mimeType;
            }

            public function thumbnail(string $sourcePath, int $longEdge): ?string
            {
                return $this->result;
            }
        };
    }
}
