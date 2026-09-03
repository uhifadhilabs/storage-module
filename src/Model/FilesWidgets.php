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

namespace Uhifadhi\Storage\Model;

use Uhifadhi\Model\Widget;
use Uhifadhi\Model\WidgetCatalog;
use Uhifadhi\Model\WidgetGroup;
use Uhifadhi\Model\WidgetPreset;

/**
 * The Files surface, as the host's widget framework reads it.
 *
 * THE TWIN OF THE DESIGN'S OWN DECLARATION. Every id, label, span, note and
 * preset layout below is transcribed from the settled design's
 * files/files.widgets.js; that file's `html` entries are the static twins of the
 * Twig partials in templates/files/. Change one of the three and change all
 * three, or the library's preview stops being the widget.
 *
 * THE MODEL THIS CATALOGUE IS ABOUT. A file is OWNER-BOUND: it belongs to a
 * RECORD in a MODULE — an observation's photograph, an incident's evidence,
 * later a permit's document. This hub BROWSES and MANAGES files across every
 * module; it is NOT a shared library you upload into, which is why there is no
 * upload control anywhere on the surface and why every tile carries its owner.
 *
 * WORDS. Written for a ranger and a warden, not an engineer: FILES, WHERE THE
 * BYTES ARE, SMALL PICTURE, OWNER. Never "storage backend", "media asset",
 * "blob", "object", "adapter", "bucket".
 *
 * Static rather than a service, exactly as the host's own catalogues are: a
 * catalogue is a statement of what a surface ships, it has no dependencies, and
 * nothing may vary it at runtime.
 */
final class FilesWidgets
{
    public const string SURFACE = 'files';

    public static function catalog(): WidgetCatalog
    {
        return new WidgetCatalog(
            self::SURFACE,
            self::groups(),
            self::widgets(),
            self::presets(),
            defaultLabel: 'The files hub',
            defaultDescription: 'What the platform ships with: the four counts, then every file with its filters, then whatever is still waiting for a small picture. The direction-neutral screen — adopt one of the five below to lead with something sharper.',
        );
    }

    /**
     * The library's headed sections ARE the five directions the file hub was
     * drawn in; each description is the gallery's own trade-off line, written
     * once and reused by the preset of the same id.
     *
     * @return list<WidgetGroup>
     */
    public static function groups(): array
    {
        $rows = [];
        foreach (self::directions() as $id => $direction) {
            $rows[] = new WidgetGroup($id, $direction[0], $direction[1]);
        }

        return $rows;
    }

    /**
     * @return list<Widget>
     */
    public static function widgets(): array
    {
        return [
            new Widget('kpis', 'The four counts', 'e', 12, [12, 9, 6], true, 'Files kept, space used, small pictures made, and what arrived this week.'),
            new Widget('browse', 'Browse every file', 'a', 12, [12], true, 'The whole hub in one widget: the filter row, the search, the grid/list toggle and every file that survives them.'),
            new Widget('recent', 'Just arrived', 'a', 6, [12, 9, 6], true, 'The newest files by the time they reached us — the widget that answers “did the patrol sync yet”.'),
            new Widget('byowner', 'The records that hold files', 'b', 12, [12], false, 'Each record with its own files under it, the way the model actually stores them.'),
            new Widget('owners', 'Modules holding files', 'b', 6, [12, 6], false, 'One row per module: how many records hold files, how many files, how much space — and which modules are not built yet.'),
            new Widget('ledger', 'The file ledger', 'c', 12, [12], false, 'Every file as a row, with its type, its size, its storage and its thumbnail state.'),
            new Widget('nothumb', 'Waiting for a small picture', 'c', 6, [12, 6], true, 'The thumbnail queue and the few that could not be made, with the reason and a way to try again.'),
            new Widget('kinds', 'What these files are', 'c', 6, [12, 6], false, 'Photographs, documents and track exports, as a share of everything kept.'),
            new Widget('byday', 'Day by day', 'd', 12, [12], false, 'Files under the day the handset took them, newest day first, drawn small so a week fits.'),
            new Widget('arrivals', 'What arrives each week', 'd', 6, [12, 6], false, 'Eight weeks of arrivals, so “are we growing” is a glance rather than a calculation.'),
            new Widget('bymodule', 'Space by module', 'e', 6, [12, 6], false, 'How much of the bill each module is, and how little of it the thumbnails are.'),
            new Widget('bybackend', 'Where the bytes are', 'e', 6, [12, 6], false, 'Each storage the host configured, what it holds and how much room is left.'),
            new Widget('biggest', 'The biggest files', 'e', 6, [12, 6], false, 'The largest originals, with their owners — the first place to look when a module’s share jumps.'),
        ];
    }

    /**
     * One preset per direction; the description IS the gallery's trade-off line,
     * so what the design said about a direction is what the product says about
     * it. Built-ins are immutable — a person copies one to edit it.
     *
     * @return list<WidgetPreset>
     */
    public static function presets(): array
    {
        $layouts = [
            'a' => ['browse' => 12, 'recent' => 12],
            'b' => ['byowner' => 12, 'owners' => 6, 'kpis' => 6],
            'c' => ['ledger' => 12, 'nothumb' => 6, 'kinds' => 6],
            'd' => ['byday' => 12, 'arrivals' => 6, 'kpis' => 6],
            'e' => ['kpis' => 12, 'bymodule' => 6, 'bybackend' => 6, 'biggest' => 12],
        ];

        $rows = [];
        foreach (self::directions() as $id => $direction) {
            $rows[] = new WidgetPreset($id, $direction[0], $direction[1], $layouts[$id]);
        }

        return $rows;
    }

    /**
     * The five directions, stated ONCE. The group and the preset of the same id
     * share these words on purpose: the library's headed section and the preset
     * that composes it are the same idea seen twice, and two copies of a
     * sentence are two chances to drift.
     *
     * @return array<string, array{string, string}>
     */
    private static function directions(): array
    {
        return [
            'a' => [
                'Contact sheet',
                'Every file as a picture, wall to wall, with its owner written under it and one filter row over the lot. The fastest way to find a photograph you half-remember; it tells you almost nothing about the files themselves.',
            ],
            'b' => [
                'Owner first',
                'Files grouped under the record that owns them — OBS-0214 and its four photographs, INC-0313 and its five. The only direction shaped like the model, and the one that teaches it; a big area is a very long page.',
            ],
            'c' => [
                'The ledger',
                'Names, sizes, types, storages and thumbnail states in one dense table. The only direction that answers “what has no small picture” and “what is still on our own disk”; you cannot see a single photograph from it.',
            ],
            'd' => [
                'By the day it was taken',
                'The field’s own calendar: files under the day the handset recorded them, never the day they uploaded. Reads like a diary of what the area saw; blind to documents, which have no such day.',
            ],
            'e' => [
                'Where the bytes are',
                'Space by module, by area and by storage, with the arrival rate and the biggest files. The direction an administrator opens before buying more disk; nobody finds a photograph with it.',
            ],
        ];
    }
}
