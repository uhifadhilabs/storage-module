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

namespace Uhifadhi\Storage\Enum;

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
    /**
     * The types a GPX arrives under. fileinfo reads bytes, not extensions, and
     * a track file is XML to it — so an allowlist that means to take tracks has
     * to carry these, and everything that reasons about KINDS has to know they
     * are a carrier rather than a kind.
     *
     * @var list<string>
     */
    public const array TRACK_CARRIERS = ['application/xml', 'text/xml'];

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
     * The shorter word the WHAT pills print.
     *
     * A pill sits in a crowded row and is read at a glance, so it says "Photos"
     * where the widget heading a table of them says "Photographs". Two words for
     * one kind because two places need different lengths of it, and the design
     * writes both.
     */
    public function pill(): string
    {
        return match ($this) {
            self::Photo => 'Photos',
            self::Document => 'Documents',
            self::Track => 'Tracks',
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
     * The kind a type is RECOGNISABLY of, or null where nothing here can say.
     *
     * The difference from {@see fromMimeType()} is the whole point: that one
     * falls back to Document, which is right for a STORED file — it got past the
     * allowlist, so the deployment accepts it and the hub shows it rather than
     * hiding it behind a kind it refuses to name. A REFUSED file got past
     * nothing, and calling an unknown binary a document in order to then tell
     * somebody it is not one would be two guesses in one sentence.
     */
    public static function recognise(string $mimeType): ?self
    {
        if (str_starts_with($mimeType, 'image/')) {
            return self::Photo;
        }

        if (str_contains($mimeType, 'gpx') || str_contains($mimeType, 'gps')) {
            return self::Track;
        }

        // The one document type this platform names positively — the signed
        // form a money case carries, and the only non-image on the shipped
        // default. Anything else is nobody's kind until somebody says so.
        return 'application/pdf' === $mimeType ? self::Document : null;
    }

    /**
     * THE NOUN A REFUSAL USES — "that file is not <noun>".
     *
     * Longer than {@see label()} on purpose: a chip in a crowded row says
     * "track", a sentence explaining why a file was turned away says "GPX
     * track", because the person reading it is holding a file and needs to know
     * which one would have worked.
     */
    public function noun(): string
    {
        return match ($this) {
            self::Photo => 'photograph',
            self::Document => 'document',
            self::Track => 'GPX track',
        };
    }

    /**
     * WHAT AN ALLOWLIST AMOUNTS TO, in kinds, in the hub's own order.
     *
     * The one place a list of mime types is turned into kinds, so the zone's
     * "jpg · png · gpx" line, the settings page's three rows and the sentence a
     * refused file gets can never disagree about what a deployment or a target
     * takes.
     *
     * BARE XML IS A CARRIER, NOT A KIND. fileinfo reads BYTES and has never
     * heard of GPX, so a real track file detects as `text/xml`; those two types
     * are on an allowlist only so a track can get through. Where a list already
     * names a GPX type they contribute nothing of their own — counting them
     * would advertise "xml" on a zone that takes tracks, and answer "not a GPX
     * track or document" to somebody who dropped a photograph.
     *
     * @param list<string> $mimeTypes
     *
     * @return list<self>
     */
    public static function inMimeTypes(array $mimeTypes): array
    {
        $kinds = [];
        foreach ($mimeTypes as $mimeType) {
            if (self::isCarrier($mimeType, $mimeTypes)) {
                continue;
            }
            $kinds[] = self::fromMimeType($mimeType);
        }

        // The hub's own order, whatever order the allowlist was written in.
        return array_values(array_filter(
            [self::Photo, self::Document, self::Track],
            static fn (self $kind): bool => \in_array($kind, $kinds, true),
        ));
    }

    /**
     * Is this type on the list only as the way another kind arrives?
     *
     * @param list<string> $mimeTypes the whole list it sits in
     */
    public static function isCarrier(string $mimeType, array $mimeTypes): bool
    {
        if (!\in_array($mimeType, self::TRACK_CARRIERS, true)) {
            return false;
        }

        foreach ($mimeTypes as $candidate) {
            if (self::Track === self::fromMimeType($candidate)) {
                return true;
            }
        }

        // Bare XML with no track beside it is just XML, and is a document.
        return false;
    }

    /**
     * "a photograph", "a photograph or document", "a photograph, document or
     * GPX track" — one phrase, with the article, ready to drop into a sentence.
     *
     * @param list<self> $kinds
     */
    public static function phrase(array $kinds): string
    {
        $nouns = array_map(static fn (self $kind): string => $kind->noun(), $kinds);

        if ([] === $nouns) {
            // An allowlist that amounts to no kind at all cannot happen — an
            // empty one is refused at construction — but a sentence with a hole
            // in it is worse than a vague one.
            return 'a kind this accepts';
        }

        $last = array_pop($nouns);

        return 'a '.([] === $nouns ? $last : implode(', ', $nouns).' or '.$last);
    }

    /**
     * Only a photograph has anything to shrink.
     */
    public function shrinkable(): bool
    {
        return self::Photo === $this;
    }
}
