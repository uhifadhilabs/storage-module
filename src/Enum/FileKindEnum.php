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

namespace UhifadhiLabs\Storage\Enum;

/**
 * What a file IS, in the words the hub uses.
 *
 * Three kinds and no more: the design's own filter row offers exactly
 * "Photos / Documents / Tracks", and a fourth kind nobody can name would be a
 * chip nobody can press. The kind is decided from the DETECTED mime type, never
 * from the file's name — renaming a thing does not change what it is.
 */
enum FileKindEnum: string
{
    case Photo = 'photo';
    case Document = 'document';
    case Track = 'track';

    /**
     * The word the filter row and the ledger column print.
     */
    public function label(): string
    {
        return match ($this) {
            self::Photo => 'photo',
            self::Document => 'document',
            self::Track => 'track',
        };
    }

    /**
     * The plural the "What these files are" widget heads its rows with.
     */
    public function plural(): string
    {
        return match ($this) {
            self::Photo => 'Photographs',
            self::Document => 'Documents',
            self::Track => 'Track exports',
        };
    }

    /**
     * Read the kind off a detected mime type.
     *
     * Anything not recognised is a document: a file that got past the storage's
     * allowlist is by definition something the deployment accepts, so the hub
     * shows it rather than hiding it behind a kind it refuses to name.
     */
    public static function fromMimeType(string $mimeType): self
    {
        if (str_starts_with($mimeType, 'image/')) {
            return self::Photo;
        }

        if (str_contains($mimeType, 'gpx') || str_contains($mimeType, 'gps')) {
            return self::Track;
        }

        return self::Document;
    }

    /**
     * Only a photograph has anything to shrink.
     */
    public function shrinkable(): bool
    {
        return self::Photo === $this;
    }
}
