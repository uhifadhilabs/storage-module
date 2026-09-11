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
 * ONE OPTION IN THE FILTER ROW — a line in a dropdown panel, or a pill.
 *
 * It carries everything the row needs to draw it and nothing else: what to
 * print, whether it is the current choice, how many files it would leave, and
 * the QUERY that chooses it. The query is the whole point — an option is a link
 * to this hub with one key replaced, so the panel needs no scripting to filter
 * and a chosen option is a URL somebody can send.
 *
 * The count is null for the two fixed pill sets, which the design draws without
 * one; for the four dropdowns it is computed against every OTHER active filter,
 * so a panel never promises files that another chip has already excluded.
 */
final readonly class FileFacet
{
    /**
     * @param string                $value the filter value, or "" for the option that lets the filter go
     * @param string                $label what to print, in the design's words
     * @param array<string, string> $query the hub's query with this option chosen
     */
    public function __construct(
        public string $value,
        public string $label,
        public bool $on,
        public array $query,
        public ?int $count = null,
    ) {
    }
}
