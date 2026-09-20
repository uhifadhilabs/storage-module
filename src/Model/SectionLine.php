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
 * ONE STATED LINE — a label on the left, a quiet note on the right, and the
 * tone the note is read in.
 *
 * The house's stated row, as data: it is how a card states facts without turning
 * into a table nobody can scan.
 */
final readonly class SectionLine
{
    public function __construct(
        public string $label,
        public string $note = '',
        public ?string $tone = null,
    ) {
    }
}
