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
 * What may be done to a file — the OWNING RECORD's answer, never storage's.
 *
 * The hub browses and manages; it does not overrule. An incident still in
 * progress will not let go of its evidence, a patrol will not let go of its own
 * track, and a document another department uploaded is not yours to remove. The
 * four answers below are the four the design draws, and the hub only repeats
 * whichever one the owning module gives.
 */
enum GuardStateEnum: string
{
    /** The record refuses: nothing attached to it may be removed yet. */
    case Locked = 'locked';
    /** Removable, but the record wants a reason written onto its own trail. */
    case Reason = 'reason';
    /** Removable. */
    case Allowed = 'allowed';
    /** Removable by somebody, but not by this person. */
    case Denied = 'denied';

    /**
     * Whether the removal control is drawn at all. The button is drawn BY the
     * guard state, never greyed out beside it: a control you may not press is
     * worse than no control, because it does not say why.
     */
    public function offersRemoval(): bool
    {
        return self::Allowed === $this || self::Reason === $this;
    }

    /**
     * The CSS modifier files.css draws this state with. Reason and Allowed are
     * both the affirmative look; they differ in what they ask for, not in what
     * they permit.
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::Locked => 'locked',
            self::Denied => 'denied',
            self::Reason, self::Allowed => 'allowed',
        };
    }
}
