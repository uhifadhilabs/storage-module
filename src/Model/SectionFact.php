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
 * ONE FRAGMENT OF THE IDENTITY BAND — the slim strip under the tab strip that
 * states what this section IS before it states how it is doing.
 *
 * Fragments, never sentences: a band that argued would be a paragraph in a
 * place nobody reads paragraphs.
 */
final readonly class SectionFact
{
    public function __construct(
        public string $label,
        public string $value,
        public ?string $qualifier = null,
    ) {
    }
}
