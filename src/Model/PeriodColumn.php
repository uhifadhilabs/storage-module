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
 * ONE PERIOD OF THE ARRIVAL CHART — a month, and how many files each module
 * that stores any put here inside it.
 *
 * A PERIOD IS A MONTH EVERYWHERE IN THIS PRODUCT, and twelve of them is how an
 * arrival rate is read: a shorter window answers "what happened" and this
 * chart answers "is it growing".
 *
 * A MODULE WITH NO BAR IS A MODULE THAT DECLARES NO FILE STORE, not a module
 * with no files — the series is keyed by the declarations, so an absent series
 * and an empty one are different drawings.
 */
final readonly class PeriodColumn
{
    /**
     * @param array<string, int> $files how many arrived, keyed by module slug
     */
    public function __construct(
        public string $label,
        public array $files,
    ) {
    }

    public function of(string $slug): int
    {
        return $this->files[$slug] ?? 0;
    }

    public function total(): int
    {
        return array_sum($this->files);
    }
}
