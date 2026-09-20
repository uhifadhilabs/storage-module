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
 * WHERE A SWITCH HAS GOT TO.
 *
 * SWITCHING IS TWO STEPS, and this enum is why: naming a new target moves
 * nothing by itself, so a switch lands in {@see Asking} and waits. The
 * question — "move the existing files so everything lives in one place?" — is
 * the whole of step two, and BOTH answers are ordinary: {@see Declined} is not
 * an error state and is never drawn as one.
 */
enum MoveStateEnum: string
{
    /** Switched, and the move question has not been answered yet. */
    case Asking = 'asking';

    /** Answered yes. Files are being moved, one at a time. */
    case Running = 'running';

    /** Answered yes, then paused. Resumable exactly where it stopped. */
    case Paused = 'paused';

    /** Every file reached the new place. The old one holds nothing. */
    case Done = 'done';

    /**
     * Answered no. Two places hold files, both are named on the tab, and the
     * offer to move them is still standing. Nothing is wrong here.
     */
    case Declined = 'declined';

    public function isMoving(): bool
    {
        return self::Running === $this || self::Paused === $this;
    }

    /** Whether the move question is still the thing the page is asking. */
    public function isAsking(): bool
    {
        return self::Asking === $this;
    }

    /** Whether more files can still be asked for. */
    public function isResumable(): bool
    {
        return self::Paused === $this || self::Declined === $this;
    }
}
