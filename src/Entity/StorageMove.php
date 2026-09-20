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

namespace Uhifadhi\Storage\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Uhifadhi\Storage\Enum\MoveStateEnum;
use Uhifadhi\Storage\Repository\StorageMoveRepository;

/**
 * ONE SWITCH, AND WHAT WAS DECIDED ABOUT THE FILES ALREADY KEPT.
 *
 * A SWITCH IS NOT A MOVE. Naming a new target writes this row in
 * {@see MoveStateEnum::Asking} and moves nothing; the question is step two,
 * and until it is answered the tab draws the question rather than a progress
 * bar. Both answers are ordinary — declining is a state, not a failure.
 *
 * THE COUNTERS ARE A READING, NOT THE TRUTH. What is really left to move is
 * the number of {@see FileLocation} rows still naming the old place, which is
 * what the job reads and what the foot line prints. These totals are kept so
 * the page can draw a bar and a rate without counting four thousand rows on
 * every render, and they are refreshed from the rows rather than trusted.
 */
#[ORM\Entity(repositoryClass: StorageMoveRepository::class)]
#[ORM\Table(name: 'storage_move')]
#[ORM\Index(name: 'idx_storage_move_state', columns: ['state'])]
class StorageMove
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\Column(length: 64)]
    private string $fromPlaceId;

    #[ORM\Column(length: 64)]
    private string $toPlaceId;

    #[ORM\Column(length: 16, enumType: MoveStateEnum::class)]
    private MoveStateEnum $state;

    /** What there was to move when the question was asked. */
    #[ORM\Column]
    private int $totalFiles = 0;

    /*
     * BIGINT, BECAUSE BYTES OVERRUN AN INT AT TWO GIGABYTES. A photograph
     * archive is measured in tens of gigabytes; an INT total would silently
     * wrap somewhere in the first week of a real installation.
     */
    #[ORM\Column(type: Types::BIGINT)]
    private int $totalBytes = 0;

    #[ORM\Column]
    private int $movedFiles = 0;

    #[ORM\Column(type: Types::BIGINT)]
    private int $movedBytes = 0;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    /** When the LAST file landed, so a rate can be read off two instants. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $progressedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct(
        string $fromPlaceId,
        string $toPlaceId,
        int $totalFiles = 0,
        int $totalBytes = 0,
        ?\DateTimeImmutable $startedAt = null,
    ) {
        $this->fromPlaceId = $fromPlaceId;
        $this->toPlaceId = $toPlaceId;
        $this->state = MoveStateEnum::Asking;
        $this->totalFiles = max(0, $totalFiles);
        $this->totalBytes = max(0, $totalBytes);
        $this->startedAt = $startedAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromPlaceId(): string
    {
        return $this->fromPlaceId;
    }

    public function getToPlaceId(): string
    {
        return $this->toPlaceId;
    }

    public function getState(): MoveStateEnum
    {
        return $this->state;
    }

    public function getTotalFiles(): int
    {
        return $this->totalFiles;
    }

    public function getTotalBytes(): int
    {
        return $this->totalBytes;
    }

    public function getMovedFiles(): int
    {
        return $this->movedFiles;
    }

    public function getMovedBytes(): int
    {
        return $this->movedBytes;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getProgressedAt(): ?\DateTimeImmutable
    {
        return $this->progressedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    /** Answered yes. From here the job asks the rows what is left. */
    public function start(?\DateTimeImmutable $at = null): void
    {
        $this->state = MoveStateEnum::Running;
        $this->progressedAt = $at ?? new \DateTimeImmutable();
    }

    public function pause(): void
    {
        $this->state = MoveStateEnum::Paused;
    }

    /** Answered no, or paused and never resumed. The offer still stands. */
    public function decline(): void
    {
        $this->state = MoveStateEnum::Declined;
    }

    public function finish(?\DateTimeImmutable $at = null): void
    {
        $this->state = MoveStateEnum::Done;
        $this->finishedAt = $at ?? new \DateTimeImmutable();
    }

    /** One file landed. Called after the copy is verified, never before. */
    public function recordMoved(int $bytes, ?\DateTimeImmutable $at = null): void
    {
        ++$this->movedFiles;
        $this->movedBytes += max(0, $bytes);
        $this->progressedAt = $at ?? new \DateTimeImmutable();
    }

    /**
     * The counters, re-read from the rows that are the actual truth.
     */
    public function reconcile(int $remainingFiles, int $remainingBytes): void
    {
        $this->movedFiles = max(0, $this->totalFiles - $remainingFiles);
        $this->movedBytes = max(0, $this->totalBytes - $remainingBytes);
    }

    /** How far along, as the bar draws it. Null when there was nothing to move. */
    public function share(): ?float
    {
        if ($this->totalFiles <= 0) {
            return null;
        }

        return round(min($this->movedFiles, $this->totalFiles) / $this->totalFiles * 100, 1);
    }
}
