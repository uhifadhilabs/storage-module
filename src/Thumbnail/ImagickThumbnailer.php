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

namespace Uhifadhi\Storage\Thumbnail;

/**
 * The preferred engine: it resamples better than GD and, where the system
 * ImageMagick was built with libheif, it is the only one of the two that can
 * read the HEIC an iPhone produces by default.
 *
 * "Where it was built with libheif" is why canDecode() interrogates
 * Imagick::queryFormats() instead of carrying a list. The same PHP extension
 * answers differently on two machines, and guessing would mean promising a
 * thumbnail this box cannot make.
 */
final class ImagickThumbnailer implements ThumbnailerInterface
{
    /**
     * MIME type to the ImageMagick format name queryFormats() answers about.
     *
     * The map is an ALLOWLIST, not a convenience: ImageMagick happily decodes
     * PDF, SVG and Postscript through delegates that have a long history of
     * being the wrong thing to point at untrusted input. Evidence is
     * photographs, so only photographs are ever handed to it.
     *
     * @var array<string, string>
     */
    private const array FORMATS = [
        'image/jpeg' => 'JPEG',
        'image/png' => 'PNG',
        'image/webp' => 'WEBP',
        'image/avif' => 'AVIF',
        'image/gif' => 'GIF',
        'image/heic' => 'HEIC',
        'image/heif' => 'HEIF',
    ];

    private const int QUALITY = 78;

    public function isAvailable(): bool
    {
        return class_exists(\Imagick::class);
    }

    public function canDecode(string $mimeType): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $format = self::FORMATS[$mimeType] ?? null;
        if (null === $format) {
            return false;
        }

        return [] !== \Imagick::queryFormats($format);
    }

    public function thumbnail(string $sourcePath, int $longEdge): ?string
    {
        if (!$this->isAvailable() || !is_file($sourcePath)) {
            return null;
        }

        try {
            $image = new \Imagick();
            // Read the FIRST frame only. A HEIC burst or an animated GIF is a
            // sequence, and thumbnailing the whole sequence would write a
            // multi-frame JPEG that nothing sensible renders.
            $image->readImage($sourcePath.'[0]');

            [$targetWidth, $targetHeight] = ThumbnailGenerator::scaleToLongEdge(
                $image->getImageWidth(),
                $image->getImageHeight(),
                $longEdge,
            );

            // Honour the EXIF orientation before resizing, then clear it: a
            // phone photo is very often stored sideways with a flag saying so,
            // and a preview that ignores the flag comes out rotated.
            $image->autoOrient();
            $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);

            $image->resizeImage($targetWidth, $targetHeight, \Imagick::FILTER_LANCZOS, 1);
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(self::QUALITY);
            // Strip metadata: a preview does not need the GPS coordinates of an
            // anti-poaching patrol embedded in it.
            $image->stripImage();

            $bytes = $image->getImageBlob();
            $image->clear();

            return '' === $bytes ? null : $bytes;
        } catch (\Throwable) {
            // \Throwable, not \ImagickException: without ext-imagick installed
            // that class does not exist to catch, and the branch would be dead.
            // Any failure is a null. See ThumbnailerInterface: a convenience
            // must never cost a photograph.
            return null;
        }
    }
}
