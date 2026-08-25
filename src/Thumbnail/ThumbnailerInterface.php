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

namespace UhifadhiLabs\Storage\Thumbnail;

/**
 * One image library, asked three honest questions.
 *
 * The three are separate on purpose. "Is the extension loaded" and "can this
 * BUILD of it decode HEIC" are genuinely different facts — an ImageMagick
 * without libheif is present and useless for an iPhone photo — and only asking
 * both lets the generator fall through to the next engine instead of failing.
 */
interface ThumbnailerInterface
{
    /** Is the underlying extension loaded on this machine at all? */
    public function isAvailable(): bool;

    /**
     * Can THIS BUILD decode that type? Asked of the library's own registry, not
     * of a hard-coded list, because the answer varies per machine.
     */
    public function canDecode(string $mimeType): bool;

    /**
     * JPEG bytes scaled so the long edge is at most $longEdge, or NULL if the
     * attempt failed.
     *
     * Never throws. A thumbnail is a convenience, and a convenience must not be
     * able to cost a ranger their photograph.
     */
    public function thumbnail(string $sourcePath, int $longEdge): ?string;
}
