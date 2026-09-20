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

namespace Uhifadhi\Storage\Message;

/**
 * CARRY ONE FILE FROM THE OLD PLACE TO THE NEW ONE.
 *
 * ONE MESSAGE PER FILE, and that is the whole reason the move survives a
 * restart: there is no long-running process holding progress in memory. A
 * crash loses at most the file in flight, and the row for a file that already
 * landed is simply not asked for again.
 *
 * IT CARRIES NO BYTES AND NO HANDLE — only the key and the two place ids, so
 * a message sitting in a queue over a deploy is still meaningful afterwards.
 * Where the file actually is remains the row's answer, re-read by the handler.
 */
final readonly class MoveStoredFile
{
    public function __construct(
        public string $key,
        public string $fromPlaceId,
        public string $toPlaceId,
    ) {
    }
}
