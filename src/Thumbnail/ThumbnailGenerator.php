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
 * Picks an engine and gets one preview out of it, or admits it could not.
 *
 * Engines are tried IN ORDER (Imagick, then GD) and an engine is skipped when
 * it is absent, when its build cannot decode the format, or when it tries and
 * fails. Running out of engines is a null — never an exception. The rule that
 * governs this whole namespace: an upload must never fail because of a
 * thumbnail.
 */
final class ThumbnailGenerator
{
    /**
     * @param list<ThumbnailerInterface> $engines in preference order
     */
    public function __construct(
        private readonly array $engines,
        private readonly int $longEdge,
    ) {
        if ($longEdge < 1) {
            throw new \InvalidArgumentException('A thumbnail needs a positive long edge.');
        }
    }

    /**
     * @return string|null JPEG bytes, or NULL when no engine on this machine
     *                     could produce one. Null is a legitimate outcome and
     *                     callers must record it honestly (StoredFile::$thumbKey).
     */
    public function generate(string $sourcePath, string $sourceMimeType): ?string
    {
        foreach ($this->engines as $engine) {
            if (!$engine->isAvailable() || !$engine->canDecode($sourceMimeType)) {
                continue;
            }

            $bytes = $engine->thumbnail($sourcePath, $this->longEdge);
            if (null !== $bytes) {
                return $bytes;
            }
            // It claimed the format and still failed — corrupt bytes, most
            // likely. Let the next engine have a go rather than giving up.
        }

        return null;
    }

    /**
     * Scale so the LONG edge becomes $longEdge, preserving aspect ratio, and
     * never upscaling: a photo already smaller than the target is left alone,
     * because blowing it up costs bytes and shows a viewer nothing new.
     *
     * @return array{int<1, max>, int<1, max>} both edges are guaranteed positive, which is
     *                                         what the image libraries require of a canvas
     */
    public static function scaleToLongEdge(int $width, int $height, int $longEdge): array
    {
        if ($width < 1 || $height < 1) {
            throw new \InvalidArgumentException('An image with no area cannot be scaled.');
        }

        $longest = max($width, $height);
        if ($longest <= $longEdge) {
            return [max(1, $width), max(1, $height)];
        }

        $ratio = $longEdge / $longest;

        // At least one pixel each way: a 4000x3 panorama must not round to zero.
        return [max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio))];
    }
}
