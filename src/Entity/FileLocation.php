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
use Uhifadhi\Storage\Repository\FileLocationRepository;

/**
 * WHERE ONE FILE ACTUALLY IS.
 *
 * WITHOUT THIS ROW A MOVE CANNOT BE RESUMED AND A READ CANNOT BE SERVED. Once
 * an installation has written to two places over its life, the key alone no
 * longer says which place holds the bytes — and guessing wrong is a record
 * losing its evidence. So every stored file records its place, and the move
 * job rewrites that row as the last step of each file, after the copy has
 * been verified and before the original is dropped.
 *
 * IT IS WRITTEN PER FILE, DELIBERATELY. A move that recorded progress only as
 * a counter would restart from nothing after a crash; a row per file means
 * the job asks "what is still in the old place" and picks up exactly there.
 *
 * THE KEY IS THE IDENTITY. It is the same key the owning module was handed
 * when it stored the file, so nothing here needs to know what a photograph is
 * attached to.
 */
#[ORM\Entity(repositoryClass: FileLocationRepository::class)]
#[ORM\Table(name: 'storage_file_location')]
#[ORM\UniqueConstraint(name: 'uniq_storage_file_location_key', columns: ['file_key'])]
#[ORM\Index(name: 'idx_storage_file_location_place', columns: ['place_id'])]
class FileLocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    /** The storage key, as the owning module was handed it. */
    #[ORM\Column(name: 'file_key', length: 512)]
    private string $key;

    #[ORM\Column(length: 64)]
    private string $placeId;

    /** What the file weighs, so a move can report bytes without opening it. */
    #[ORM\Column(type: Types::BIGINT)]
    private int $byteSize;

    #[ORM\Column]
    private \DateTimeImmutable $recordedAt;

    public function __construct(string $key, string $placeId, int $byteSize = 0, ?\DateTimeImmutable $recordedAt = null)
    {
        $this->key = $key;
        $this->placeId = $placeId;
        $this->byteSize = max(0, $byteSize);
        $this->recordedAt = $recordedAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getPlaceId(): string
    {
        return $this->placeId;
    }

    public function getByteSize(): int
    {
        return $this->byteSize;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    /** The last step of moving one file: it is now in the new place. */
    public function moveTo(string $placeId, ?\DateTimeImmutable $at = null): void
    {
        $this->placeId = $placeId;
        $this->recordedAt = $at ?? new \DateTimeImmutable();
    }
}
