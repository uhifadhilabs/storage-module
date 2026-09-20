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
use Uhifadhi\Storage\Entity\StorageMove;
use Uhifadhi\Storage\Enum\MoveStateEnum;

/**
 * @extends ServiceEntityRepository<StorageMove>
 */
class StorageMoveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StorageMove::class);
    }

    /**
     * THE SWITCH THE TAB IS ABOUT — the newest one that has not finished.
     *
     * A finished move is history and draws nothing; an installation that has
     * switched three times over three years shows the state of the third.
     */
    public function findOpen(): ?StorageMove
    {
        return $this->findOneBy(
            ['state' => [MoveStateEnum::Asking, MoveStateEnum::Running, MoveStateEnum::Paused, MoveStateEnum::Declined]],
            ['id' => 'DESC'],
        );
    }

    public function findLatest(): ?StorageMove
    {
        return $this->findOneBy([], ['id' => 'DESC']);
    }
}
