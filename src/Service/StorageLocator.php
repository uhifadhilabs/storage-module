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

use League\Flysystem\FilesystemOperator;
use Psr\Container\ContainerInterface;
use Uhifadhi\Storage\Repository\FileLocationRepository;

/**
 * WHICH FILESYSTEM HOLDS A GIVEN FILE.
 *
 * ONCE AN INSTALLATION HAS WRITTEN TO TWO PLACES OVER ITS LIFE, THE KEY NO
 * LONGER SAYS WHERE THE BYTES ARE. A photograph filed in June is in the place
 * that was current in June; the same key written today is in the place that is
 * current today. Guessing wrong is a record losing its evidence, so the answer
 * is read off the file's own row and never inferred.
 *
 * A FILE WITH NO ROW IS IN THE CURRENT PLACE. That is the honest default for
 * everything written before this bundle recorded locations at all: an
 * installation that never switched has exactly one place, and every file in it
 * is there. The backfill in the first migration writes those rows, so the
 * default is a safety net rather than the normal path.
 *
 * THE OPERATORS COME FROM A SERVICE LOCATOR, not from constructor arguments:
 * which storages exist is configuration, so the map is built where the
 * configuration is read and this class asks it by place id.
 */
final readonly class StorageLocator
{
    /**
     * @param ContainerInterface $operators place id => FilesystemOperator
     */
    public function __construct(
        private ContainerInterface $operators,
        private FileLocationRepository $locations,
        private StorageTargetService $targets,
    ) {
    }

    /** The place new files are written to. */
    public function current(): FilesystemOperator
    {
        return $this->of($this->targets->currentPlaceId());
    }

    /**
     * WHERE THIS FILE IS. Read off its row; the current place where it has
     * none.
     */
    public function for(string $key): FilesystemOperator
    {
        return $this->of($this->locations->findByKey($key)?->getPlaceId() ?? $this->targets->currentPlaceId());
    }

    /**
     * One named place's filesystem.
     *
     * A place the installation no longer configures is a real state — somebody
     * removed the block while rows still pointed at it — and it fails loudly
     * here rather than silently reading the wrong bucket.
     */
    public function of(?string $placeId): FilesystemOperator
    {
        if (null === $placeId || !$this->operators->has($placeId)) {
            throw new \LogicException(\sprintf('No storage is configured for the place "%s". Files recorded there cannot be read until the installation declares it again under storage.targets.', $placeId ?? '(none)'));
        }

        $operator = $this->operators->get($placeId);
        if (!$operator instanceof FilesystemOperator) {
            throw new \LogicException(\sprintf('The storage registered for "%s" is not a filesystem.', $placeId));
        }

        return $operator;
    }

    public function has(string $placeId): bool
    {
        return $this->operators->has($placeId);
    }
}
