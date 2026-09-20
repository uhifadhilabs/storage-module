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

namespace Uhifadhi\Storage\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Uhifadhi\Storage\Entity\StorageTarget;
use Uhifadhi\Storage\Enum\TargetRoleEnum;

/**
 * @extends ServiceEntityRepository<StorageTarget>
 */
class StorageTargetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StorageTarget::class);
    }

    /** The one place new files are written to, or null before a switch was ever made. */
    public function findCurrent(): ?StorageTarget
    {
        return $this->findOneBy(['role' => TargetRoleEnum::Current]);
    }

    /** The place being kept readable while it empties, if there is one. */
    public function findRetired(): ?StorageTarget
    {
        return $this->findOneBy(['role' => TargetRoleEnum::Retired]);
    }

    public function findByPlaceId(string $placeId): ?StorageTarget
    {
        return $this->findOneBy(['placeId' => $placeId]);
    }
}
