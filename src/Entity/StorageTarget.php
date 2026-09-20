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

use Doctrine\ORM\Mapping as ORM;
use Uhifadhi\Storage\Enum\TargetRoleEnum;
use Uhifadhi\Storage\Repository\StorageTargetRepository;

/**
 * A NAMED PLACE, AND WHAT IT IS TO THIS INSTALLATION RIGHT NOW.
 *
 * THE PLACE ITSELF IS CONFIGURATION AND THIS ROW IS NOT. Credentials, the
 * bucket, the directory: those are the installation's own configuration and
 * belong nowhere near a database. What a row here carries is the one thing
 * configuration cannot — which of the configured places is being written to
 * TODAY, and which one is being kept readable while it empties. A switch is a
 * decision somebody made at a moment, so it is a fact with a date, not a line
 * in a file.
 *
 * ONE CURRENT, AT MOST ONE RETIRED, enforced by a partial unique index per
 * role rather than by hope: two writable targets would scatter files by
 * accident of timing and no screen could answer "where is it".
 */
#[ORM\Entity(repositoryClass: StorageTargetRepository::class)]
#[ORM\Table(name: 'storage_target')]
#[ORM\UniqueConstraint(name: 'uniq_storage_target_place', columns: ['place_id'])]
/*
 * ONE CURRENT AND ONE RETIRED, ENFORCED BY THE DATABASE rather than by hope.
 * Expressed as PARTIAL unique indexes — `WHERE role = …` — because the
 * constraint is not "role is unique" but "there is at most one row in each of
 * these two roles". Declared here and not only in the migration so that an
 * installation's own `doctrine:migrations:diff` agrees with what this bundle
 * shipped instead of offering to drop them.
 */
#[ORM\UniqueConstraint(name: 'uniq_storage_target_current', columns: ['role'], options: ['where' => "((role)::text = 'current'::text)"])]
#[ORM\UniqueConstraint(name: 'uniq_storage_target_retired', columns: ['role'], options: ['where' => "((role)::text = 'retired'::text)"])]
class StorageTarget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    /** The configured place's id — the key the installation named it under. */
    #[ORM\Column(length: 64)]
    private string $placeId;

    #[ORM\Column(length: 16, enumType: TargetRoleEnum::class)]
    private TargetRoleEnum $role;

    /** When this place took this role. A switch is a dated fact. */
    #[ORM\Column]
    private \DateTimeImmutable $since;

    public function __construct(string $placeId, TargetRoleEnum $role, ?\DateTimeImmutable $since = null)
    {
        $this->placeId = $placeId;
        $this->role = $role;
        $this->since = $since ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlaceId(): string
    {
        return $this->placeId;
    }

    public function getRole(): TargetRoleEnum
    {
        return $this->role;
    }

    public function setRole(TargetRoleEnum $role, ?\DateTimeImmutable $since = null): void
    {
        $this->role = $role;
        $this->since = $since ?? new \DateTimeImmutable();
    }

    public function getSince(): \DateTimeImmutable
    {
        return $this->since;
    }

    public function isWritable(): bool
    {
        return $this->role->isWritable();
    }
}
