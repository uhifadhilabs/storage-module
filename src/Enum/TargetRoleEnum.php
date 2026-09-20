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
 * WHAT A NAMED PLACE IS TO THIS INSTALLATION RIGHT NOW.
 *
 * AN INSTALLATION WRITES TO EXACTLY ONE PLACE. Two writable targets is the
 * state this enum exists to make unrepresentable: files would land in two
 * places by accident of timing, and no screen could answer "where is it"
 * without reading both.
 *
 * A RETIRED PLACE IS READ-ONLY, NEVER UNREACHABLE. A record filed in June
 * still hands over its photograph while a move runs and after it, until the
 * count reaches zero and an administrator clears the place deliberately.
 */
enum TargetRoleEnum: string
{
    /** The one place new files are written to. Exactly one, always. */
    case Current = 'current';

    /**
     * A place that was current and is not any more. Readable, never written
     * to, and clearable only once it holds nothing.
     */
    case Retired = 'retired';

    public function isWritable(): bool
    {
        return self::Current === $this;
    }

    /** The word the tab prints on the place's chip. */
    public function label(): string
    {
        return match ($this) {
            self::Current => 'current',
            self::Retired => 'read-only',
        };
    }
}
