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

namespace UhifadhiLabs\Storage\Service;

use UhifadhiLabs\Storage\Enum\FileKindEnum;
use UhifadhiLabs\Storage\Model\FileFilter;
use UhifadhiLabs\Storage\Registry\FileRegistry;

/**
 * Everything the thirteen widget partials read, gathered ONCE.
 *
 * The host's widget library renders each partial with `with_context: false` and
 * exactly this array, so a partial that reaches for anything not in here fails
 * loudly rather than rendering an empty widget. That is the point: the return
 * value below IS the contract between the surface and its partials, and both the
 * hub and the library hand over the same one, which is what makes the library's
 * preview the real widget.
 *
 * Every widget is a SCOPE OF ONE QUERY. The filtered set is computed once and
 * every grouping below is derived from it, so a chip pressed in the filter row
 * moves the counts, the day rail and the space bars together or not at all.
 */
final readonly class FilesSurface
{
    public function __construct(
        private FileRegistry $registry,
        private StorageSettings $settings,
    ) {
    }

    /**
     * @return array{
     *     filter: FileFilter,
     *     files: list<\UhifadhiLabs\Storage\Model\FileEntry>,
     *     total: int,
     *     counts: array{files: int, bytes: int, made: int, waiting: int, failed: int, arrived: int},
     *     modules: list<array{slug: string, label: string, attachesTo: string, records: int, files: int, bytes: int}>,
     *     areas: list<array{slug: string, label: string}>,
     *     days: list<string>,
     *     kinds: list<FileKindEnum>,
     *     byOwner: list<array{ref: string, label: string, url: string|null, moduleSlug: string, moduleLabel: string, areaLabel: string|null, day: string, files: list<\UhifadhiLabs\Storage\Model\FileEntry>}>,
     *     byDay: list<array{day: string, files: list<\UhifadhiLabs\Storage\Model\FileEntry>}>,
     *     byKind: list<array{kind: FileKindEnum, files: int, bytes: int, share: float}>,
     *     bySpace: list<array{slug: string, label: string, records: int, files: int, bytes: int, share: float}>,
     *     byArea: list<array{slug: string|null, label: string, files: int, bytes: int, share: float}>,
     *     arrivals: list<array{week: \DateTimeImmutable, files: int, bytes: int, share: float}>,
     *     recent: list<\UhifadhiLabs\Storage\Model\FileEntry>,
     *     biggest: list<\UhifadhiLabs\Storage\Model\FileEntry>,
     *     waiting: list<\UhifadhiLabs\Storage\Model\FileEntry>,
     *     places: list<\UhifadhiLabs\Storage\Model\StoragePlace>,
     *     thumbnailLongEdge: int,
     *     now: \DateTimeImmutable
     * }
     */
    public function context(FileFilter $filter, ?\DateTimeImmutable $now = null): array
    {
        // "now" is injected rather than read from a clock inside the groupings,
        // so a test can state which week "this week" is.
        $now ??= new \DateTimeImmutable();
        $files = $this->registry->filter($filter);

        return [
            'filter' => $filter,
            'files' => $files,
            'total' => \count($this->registry->all()),
            'counts' => $this->registry->counts($files, $now),
            'modules' => $this->registry->modules(),
            'areas' => $this->registry->areas(),
            'days' => $this->registry->days(),
            'kinds' => FileKindEnum::cases(),
            'byOwner' => $this->registry->byOwner($files),
            'byDay' => $this->registry->byDay($files),
            'byKind' => $this->registry->byKind($files),
            'bySpace' => $this->registry->bySpace($files),
            'byArea' => $this->registry->byArea($files),
            'arrivals' => $this->registry->arrivalsByWeek(8, $files, $now),
            'recent' => $this->registry->recent(6, $files),
            'biggest' => $this->registry->biggest(6, $files),
            'waiting' => $this->registry->withoutThumbnail($files),
            'places' => $this->settings->places(),
            'thumbnailLongEdge' => $this->settings->thumbnailLongEdge(),
            'now' => $now,
        ];
    }
}
