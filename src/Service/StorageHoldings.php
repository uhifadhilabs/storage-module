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

use Uhifadhi\Storage\Registry\FileRegistry;

/**
 * HOW MUCH EACH PLACE HOLDS — asked once, answered the same way everywhere.
 *
 * TWO SURFACES USED TO COUNT THIS TWO WAYS. The target card read the
 * per-file location rows and said "0 files"; the Storage tab attributed
 * files with no row to the current place and said "80". Both were defensible
 * and together they were a bug: a page cannot give two answers to "how much
 * is in here", and the one that says nought is the one that makes an
 * administrator think a switch lost their evidence.
 *
 * THE ATTRIBUTION IS THE LOCATOR'S, and that is what makes it the right one.
 * {@see StorageLocator} reads a file with no location row as being in the
 * CURRENT place — it has to, because that is where such a file actually is —
 * so any screen that counted them differently would be describing a world
 * the app does not act in.
 *
 * WHY FILES HAVE NO ROW. Everything written before this bundle recorded
 * locations has none, and nothing backfills them: a migration walking every
 * module's records would be a migration reaching into schemas it does not
 * own. They are attributed here instead, once, to the place they are in.
 */
final readonly class StorageHoldings
{
    public function __construct(
        private FileRegistry $registry,
        private StoragePlaces $places,
        private StorageTargetService $targets,
    ) {
    }

    /**
     * What each configured place holds, keyed by place id.
     *
     * @return array<string, array{files: int, bytes: int}>
     */
    public function all(): array
    {
        $counts = $this->registry->counts();
        $currentId = $this->targets->currentPlaceId();

        $held = [];
        $located = ['files' => 0, 'bytes' => 0];
        foreach ($this->places->all() as $place) {
            $held[$place->id] = $this->targets->remaining($place->id);
            $located['files'] += $held[$place->id]['files'];
            $located['bytes'] += $held[$place->id]['bytes'];
        }

        // THE UNLOCATED ONES GO WHERE THE LOCATOR WOULD LOOK FOR THEM.
        if (null !== $currentId && isset($held[$currentId])) {
            $held[$currentId]['files'] += max(0, $counts['files'] - $located['files']);
            $held[$currentId]['bytes'] += max(0, $counts['bytes'] - $located['bytes']);
        }

        return $held;
    }

    /**
     * One place's holding, or nothing where the installation does not
     * configure it.
     *
     * @return array{files: int, bytes: int}
     */
    public function of(?string $placeId): array
    {
        if (null === $placeId) {
            return ['files' => 0, 'bytes' => 0];
        }

        return $this->all()[$placeId] ?? ['files' => 0, 'bytes' => 0];
    }
}
