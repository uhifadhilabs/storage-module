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

use Uhifadhi\Storage\DependencyInjection\StorageConfiguration;
use Uhifadhi\Storage\Model\StoragePlace;
use Uhifadhi\Storage\Registry\FileRegistry;
use Uhifadhi\Storage\Registry\FileSourceInterface;

/**
 * "Where files go", answered from the host's own configuration and nothing else.
 *
 * The page this feeds is written for whoever administers the platform, and every
 * line on it is a FACT ABOUT THIS DEPLOYMENT: which places are configured, what
 * is allowed in, how big one file may be. Nothing is a sample and nothing is
 * aspirational — a settings page that shows what a different deployment would
 * look like is worse than no settings page.
 *
 * The module→storage map is drawn from the installed sources, so a module that
 * ships no file source shows as having nowhere to put anything, which is the
 * truth.
 */
final readonly class StorageSettings
{
    /**
     * The permission the hub's settings page rides on.
     *
     * Seeing where files are kept is seeing something about every file at once,
     * so it is the same permission as installing a module rather than one of its
     * own. Stated here as a constant so a host wires it in one place.
     */
    public const string ADMIN_PERMISSION = 'modules.manage';

    /**
     * The tag a module implements to appear on the map at all — repeated here so
     * the settings template can name it without reaching into the registry.
     */
    public const string SOURCE_TAG = FileSourceInterface::TAG;

    /**
     * @param list<string> $allowedMimeTypes
     */
    public function __construct(
        private FileRegistry $registry,
        private string $adapter,
        private string $label,
        private ?string $location,
        private array $allowedMimeTypes,
        private int $maxBytes,
        private int $thumbnailLongEdge,
    ) {
    }

    /**
     * The places files are kept.
     *
     * One, today: this bundle declares exactly one named storage,
     * "storage.evidence", and saying so plainly is more honest than drawing an
     * empty second card for a place nobody configured.
     *
     * @return list<StoragePlace>
     */
    public function places(): array
    {
        $s3 = StorageConfiguration::ADAPTER_S3 === $this->adapter;

        return [new StoragePlace(
            'evidence',
            $this->label,
            $s3 ? 's3' : 'local',
            $s3 ? 'Object storage' : 'The application’s own disk',
            $this->location,
        )];
    }

    /**
     * Which module's files go where.
     *
     * A module never names a place: it asks for "the place my files go" and the
     * host answers. That is the whole seam, and it is what lets one deployment
     * run on a disk and another on object storage without a single change inside
     * patrols or incidents.
     *
     * @return list<array{slug: string, label: string, attachesTo: string, place: StoragePlace|null}>
     */
    public function map(): array
    {
        $place = $this->places()[0];

        $rows = [];
        foreach ($this->registry->modules() as $module) {
            $rows[] = [
                'slug' => $module['slug'],
                'label' => $module['label'],
                'attachesTo' => $module['attachesTo'],
                'place' => $place,
            ];
        }

        return $rows;
    }

    /**
     * What is allowed in — the DETECTED types, grouped the way the design's
     * "Photographs / Documents / Tracks" rows read.
     *
     * @return array{photos: list<string>, documents: list<string>, tracks: list<string>}
     */
    public function allowed(): array
    {
        $rows = ['photos' => [], 'documents' => [], 'tracks' => []];
        foreach ($this->allowedMimeTypes as $mime) {
            $bucket = match (true) {
                str_starts_with($mime, 'image/') => 'photos',
                str_contains($mime, 'gpx'), str_contains($mime, 'gps') => 'tracks',
                default => 'documents',
            };
            $short = substr($mime, (int) strrpos($mime, '/') + 1);
            $rows[$bucket][] = str_replace(['x-', '+xml'], '', $short);
        }

        return $rows;
    }

    public function maxBytes(): int
    {
        return $this->maxBytes;
    }

    public function thumbnailLongEdge(): int
    {
        return $this->thumbnailLongEdge;
    }
}
