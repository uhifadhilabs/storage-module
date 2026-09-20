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
 * ONE OF THE FIVE KPI CARDS on the section's overview — a figure, what it is
 * of, the line under it, and its movement against the period before.
 *
 * NULL AND ZERO ARE DIFFERENT FACTS. A figure nothing can measure yet is
 * constructed with a value of "—" and no delta; it is never a nought, because
 * a nought is a measurement.
 */
final readonly class SectionKpi
{
    public function __construct(
        public string $label,
        public string $value,
        public ?string $of = null,
        public ?string $qualifier = null,
        public ?float $delta = null,
        public bool $hot = false,
        public ?string $warn = null,
    ) {
    }

    /** good, bad or flat — the three the pill is drawn in. */
    public function tone(): string
    {
        return match (true) {
            null === $this->delta, 0.0 === $this->delta => 'flat',
            $this->delta > 0 => 'good',
            default => 'bad',
        };
    }
}
