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
 * WHAT THE FILE BECAME, in the receiving module's own words.
 *
 * The component sends bytes and knows nothing else. What comes back is this: the
 * one sentence of fact the owning module can state about what it did — the name
 * to print, the page to open, and the word for the chip the design puts on a
 * finished row ("parsed" where something was read out of the file, "stored"
 * where the bytes themselves are the point).
 *
 * KEEPS THE BYTES IS THE INTERESTING FIELD. A boundary import parses a GPX into
 * geometry and has no further use for the file; saying so here is how the
 * storage learns to throw the blob away rather than keeping evidence nobody
 * owns. The design's word for that outcome is "parsed" and the honest reading of
 * it is "nothing kept" — the receipt states both halves so the two cannot
 * disagree.
 */
final readonly class UploadReceipt
{
    /**
     * @param string      $label      what to print — usually the name a person uploaded it under
     * @param string|null $href       where the file can be opened, or null where the module
     *                                publishes no page for it
     * @param string      $kind       the chip's word: what the module made of the file
     * @param bool        $keepsBytes whether the stored original is still wanted. FALSE means
     *                                the module took what it needed and the storage deletes
     *                                the blob and its preview.
     */
    private function __construct(
        public string $label,
        public ?string $href,
        public string $kind,
        public bool $keepsBytes,
    ) {
    }

    /** The ordinary outcome: the bytes are the thing, and they stay. */
    public static function stored(string $label, ?string $href = null, string $kind = 'stored'): self
    {
        return new self($label, $href, $kind, true);
    }

    /**
     * The file was read and is not wanted. The storage deletes the original and
     * its preview after this comes back, so nothing outlives the record that
     * would have owned it.
     */
    public static function parsed(string $label, ?string $href = null, string $kind = 'parsed'): self
    {
        return new self($label, $href, $kind, false);
    }
}
