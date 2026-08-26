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
 * Whether the ONE ~400px picture exists — and, when it does not, why not.
 *
 * Four states rather than a boolean, because a PDF with nothing to shrink, a
 * photograph still in the queue and a photograph the thumbnailer could not read
 * are three different facts and the design refuses to draw them alike. A file
 * is kept whether or not its small picture was made; the thumbnail is a
 * convenience, never a condition of storage.
 */
enum ThumbStateEnum: string
{
    /** The ~400px picture exists. */
    case Made = 'made';
    /** A photograph whose thumbnail job has not run yet. */
    case Waiting = 'wait';
    /** A photograph nothing on this machine could decode (typically HEIC without Imagick+libheif). */
    case Failed = 'failed';
    /** A document or a track: there is nothing to shrink. */
    case Nothing = 'none';

    /**
     * The pill's word, from the design's own vocabulary.
     */
    public function label(): string
    {
        return match ($this) {
            self::Made => 'thumbnail',
            self::Waiting => 'making',
            self::Failed => 'no thumbnail',
            self::Nothing => 'no picture',
        };
    }

    /**
     * The wording used where the sentence is about the file rather than the pill.
     */
    public function sentence(): string
    {
        return match ($this) {
            self::Made => 'made',
            self::Waiting => 'being made',
            self::Failed => 'could not be made',
            self::Nothing => 'nothing to shrink',
        };
    }

    /**
     * The default reading, for a source that does not track a queue: a
     * photograph with a thumb key has one, a photograph without has not, and
     * anything else never will.
     */
    public static function of(FileKindEnum $kind, ?string $thumbKey): self
    {
        if (!$kind->shrinkable()) {
            return self::Nothing;
        }

        return null === $thumbKey ? self::Failed : self::Made;
    }

    /**
     * The "Has one / Has none" chip in the filter row is this, and only this.
     */
    public function exists(): bool
    {
        return self::Made === $this;
    }
}
