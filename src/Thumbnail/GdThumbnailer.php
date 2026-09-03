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
 * The fallback engine. GD ships with almost every PHP build, which is exactly
 * what makes it the fallback and not the preference: it reads fewer formats
 * than ImageMagick and — importantly here — no GD build in existence decodes
 * HEIC, the format an iPhone produces by default.
 */
final class GdThumbnailer implements ThumbnailerInterface
{
    /**
     * Each supported type paired with the gd_info() flag that says whether THIS
     * build has it. Asking the build rather than assuming is the whole point:
     * a GD compiled without WebP would otherwise fail at decode time on a file
     * we had already promised to handle.
     *
     * @var array<string, string>
     */
    private const array DECODERS = [
        'image/jpeg' => 'JPEG Support',
        'image/png' => 'PNG Support',
        'image/webp' => 'WebP Support',
        'image/avif' => 'AVIF Support',
        'image/gif' => 'GIF Read Support',
    ];

    public function isAvailable(): bool
    {
        return \extension_loaded('gd') && \function_exists('gd_info');
    }

    public function canDecode(string $mimeType): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $flag = self::DECODERS[$mimeType] ?? null;
        if (null === $flag) {
            // Covers HEIC and HEIF, and does so permanently rather than by
            // omission: GD has no HEIF decoder to compile in.
            return false;
        }

        $info = gd_info();

        return true === ($info[$flag] ?? false);
    }

    public function thumbnail(string $sourcePath, int $longEdge): ?string
    {
        if (!$this->isAvailable() || !is_file($sourcePath)) {
            return null;
        }

        // The @ is deliberate: imagecreatefromstring() emits a warning on
        // corrupt bytes, and phpunit is configured to fail on warnings. A
        // corrupt upload is a null, not a failed test run and not a failed
        // upload.
        $bytes = @file_get_contents($sourcePath);
        if (false === $bytes) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);
        if (false === $source) {
            return null;
        }

        [$width, $height] = [imagesx($source), imagesy($source)];
        [$targetWidth, $targetHeight] = ThumbnailGenerator::scaleToLongEdge($width, $height, $longEdge);

        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        // A white ground, because the output is JPEG and JPEG has no alpha:
        // without this, a transparent PNG's clear pixels come out black.
        // A palette that will not allocate is a null like every other failure
        // here, rather than a black-backed preview nobody asked for.
        $white = imagecolorallocate($target, 255, 255, 255);
        if (false === $white) {
            return null;
        }
        imagefilledrectangle($target, 0, 0, $targetWidth - 1, $targetHeight - 1, $white);

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $written = imagejpeg($target, null, self::QUALITY);
        $out = ob_get_clean();

        // No imagedestroy(): a GdImage has been a garbage-collected object
        // since PHP 8.0, and calling it is deprecated as of 8.5 — which this
        // bundle's CI matrix runs.

        return $written && \is_string($out) && '' !== $out ? $out : null;
    }

    /** Enough for a grid preview, small enough that a field connection can load a page of them. */
    private const int QUALITY = 78;
}
