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
use Uhifadhi\Storage\Entity\FileLocation;

/**
 * @extends ServiceEntityRepository<FileLocation>
 */
class FileLocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FileLocation::class);
    }

    public function findByKey(string $key): ?FileLocation
    {
        return $this->findOneBy(['key' => $key]);
    }

    /**
     * WHAT IS STILL IN A PLACE — the count and the bytes, read off the rows
     * rather than off a counter, because the rows are what a resumed move
     * asks and a counter is what a crash loses.
     *
     * @return array{files: int, bytes: int}
     */
    public function tally(string $placeId): array
    {
        /** @var array{files: int|string|null, bytes: int|string|null} $row */
        $row = $this->createQueryBuilder('l')
            ->select('COUNT(l.id) AS files', 'COALESCE(SUM(l.byteSize), 0) AS bytes')
            ->andWhere('l.placeId = :place')
            ->setParameter('place', $placeId)
            ->getQuery()
            ->getSingleResult();

        return ['files' => (int) $row['files'], 'bytes' => (int) $row['bytes']];
    }

    /**
     * The next files still sitting in the old place, oldest first — one batch
     * of the move, asked for by the job each time it runs.
     *
     * @return list<FileLocation>
     */
    public function findByPlaceId(string $placeId, int $limit = 100): array
    {
        return $this->findBy(['placeId' => $placeId], ['id' => 'ASC'], max(1, $limit));
    }
}
