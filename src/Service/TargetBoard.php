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

namespace Uhifadhi\Storage\Service;

use Uhifadhi\Storage\Entity\StorageMove;
use Uhifadhi\Storage\Enum\MoveStateEnum;
use Uhifadhi\Storage\Model\StoragePlace;

/**
 * WHICH OF THE FIVE STATES THE STORAGE PAGE IS IN, AND WHAT IT NEEDS TO DRAW
 * IT.
 *
 * THE DESIGN DRAWS FIVE STATES SIDE BY SIDE; THE PAGE DRAWS ONE. A states
 * gallery is how a design is reviewed — five frames, one glance, every case
 * argued at once. A screen is not reviewed, it is used: an administrator is
 * in exactly one of these situations and the other four are noise. So this
 * class answers WHICH, and the template draws that one.
 *
 * The five, and what puts the page in each:
 *
 *   ONE       no old place and no switch waiting — the ordinary day
 *   ASKING    a switch was made and the move question is unanswered
 *   MOVING    the question was answered yes; files are being carried
 *   MOVED     the old place holds nothing, so it can be cleared
 *   DECLINED  the question was answered no; two places, both named
 *
 * NOTHING HERE DECIDES ANYTHING. Every count is read off the rows and every
 * rule is {@see StorageTargetService}'s; this only chooses which true thing
 * to say.
 */
final readonly class TargetBoard
{
    public const string ONE = 'one';
    public const string ASKING = 'asking';
    public const string MOVING = 'moving';
    public const string MOVED = 'moved';
    public const string DECLINED = 'declined';

    public function __construct(
        private StorageTargetService $targets,
        private StoragePlaces $places,
    ) {
    }

    /**
     * @return array{
     *     state: string,
     *     current: StoragePlace|null,
     *     retired: StoragePlace|null,
     *     alternatives: list<StoragePlace>,
     *     move: StorageMove|null,
     *     held: array{files: int, bytes: int},
     *     remaining: array{files: int, bytes: int},
     *     canClear: bool,
     *     rate: float|null,
     *     secondsLeft: int|null
     * }
     */
    public function read(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        $currentId = $this->targets->currentPlaceId();
        $retiredId = $this->targets->retired()?->getPlaceId();
        $move = $this->targets->openMove();

        $current = $this->places->find($currentId);
        $retired = $this->places->find($retiredId);
        $remaining = $this->targets->remaining($retiredId);

        return [
            'state' => $this->state($move, $retiredId, $remaining['files']),
            'current' => null === $current ? null : $current->as(true, false),
            'retired' => null === $retired ? null : $retired->as(false, true),
            'alternatives' => $this->places->alternativesTo($currentId),
            'move' => $move,
            'held' => $this->targets->remaining($currentId),
            'remaining' => $remaining,
            'canClear' => $this->targets->canClearRetired(),
            'rate' => null === $move ? null : self::rate($move),
            'secondsLeft' => null === $move ? null : self::secondsLeft($move),
        ];
    }

    private function state(?StorageMove $move, ?string $retiredId, int $remainingFiles): string
    {
        if (null !== $move) {
            // A QUESTION ABOUT NOTHING IS NOT A QUESTION. The old place can
            // empty by other means while a switch waits on an answer — files
            // removed with their records, say — and asking then would be
            // asking somebody to move nought files.
            if (MoveStateEnum::Asking === $move->getState() && 0 === $remainingFiles) {
                return self::MOVED;
            }

            return match ($move->getState()) {
                MoveStateEnum::Asking => self::ASKING,
                MoveStateEnum::Running, MoveStateEnum::Paused => self::MOVING,
                MoveStateEnum::Declined => self::DECLINED,
                // A finished move is history and is never open, so this arm
                // exists only so the match is total.
                MoveStateEnum::Done => self::MOVED,
            };
        }

        // NO OPEN SWITCH. An old place that still holds something is a
        // decline nobody recorded — an installation upgraded mid-move, say —
        // and it reads as the decline it is rather than as an ordinary day.
        if (null !== $retiredId) {
            return $remainingFiles > 0 ? self::DECLINED : self::MOVED;
        }

        return self::ONE;
    }

    /**
     * FILES A SECOND, read off two instants and the files between them.
     *
     * Null until a move has actually carried something: a rate computed from
     * one file is a number with no information in it, and the page would
     * rather say nothing than print "about 4 hours left" on no evidence.
     */
    private static function rate(StorageMove $move): ?float
    {
        $progressedAt = $move->getProgressedAt();
        if (null === $progressedAt || $move->getMovedFiles() < 2) {
            return null;
        }

        $seconds = $progressedAt->getTimestamp() - $move->getStartedAt()->getTimestamp();

        return $seconds > 0 ? round($move->getMovedFiles() / $seconds, 1) : null;
    }

    /** How long the rest would take at the rate so far, or null when nobody can say. */
    private static function secondsLeft(StorageMove $move): ?int
    {
        $rate = self::rate($move);
        if (null === $rate || $rate <= 0.0) {
            return null;
        }

        return (int) ceil(max(0, $move->getTotalFiles() - $move->getMovedFiles()) / $rate);
    }
}
