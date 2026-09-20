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

namespace Uhifadhi\Storage\Model;

/**
 * ONE RANKED BAR — a named thing, its share of the largest row, and the figure
 * read off the end of the bar rather than off an axis.
 *
 * The card that holds a run of these is bounded by the number of rows it
 * draws, never by the data.
 */
final readonly class SectionBar
{
    public function __construct(
        public string $label,
        public int $value,
        public string $note,
        public float $filledWidth = 0.0,
    ) {
    }

    /** The same row, with its width taken against the largest row's value. */
    public function scaledTo(int $largest): self
    {
        if ($largest <= 0) {
            return $this;
        }

        return new self($this->label, $this->value, $this->note, round($this->value / $largest * 100, 1));
    }

    /** A row with nothing in it is drawn quiet, and says so instead of drawing a bar. */
    public function isQuiet(): bool
    {
        return 0 === $this->value;
    }
}
