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

namespace Uhifadhi\Storage\Tests\Unit\Widget;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Storage\Widget\FilesWidgets;

/**
 * The catalogue is the TWIN of the settled design's own declaration
 * (uhifadhi-web: files/files.widgets.js). Every id, span, note and preset layout
 * asserted below was transcribed from it, so this test is where a drift between
 * the design and the product shows up.
 */
final class FilesWidgetsTest extends TestCase
{
    public function testTheSurfaceIsFiles(): void
    {
        self::assertSame('files', FilesWidgets::SURFACE);
        self::assertSame('files', new FilesWidgets()->catalog()->surface);
    }

    public function testTheLibrarysHeadedSectionsAreTheFiveDirectionsTheHubWasDrawnIn(): void
    {
        $groups = new FilesWidgets()->catalog()->groups();

        self::assertSame(['a', 'b', 'c', 'd', 'e'], array_map(static fn ($g): string => $g->id, $groups));
        self::assertSame(
            ['Contact sheet', 'Owner first', 'The ledger', 'By the day it was taken', 'Where the bytes are'],
            array_map(static fn ($g): string => $g->label, $groups),
        );
    }

    public function testTheCatalogueShipsThirteenWidgetsInTheDesignsOwnOrder(): void
    {
        self::assertSame(
            ['kpis', 'browse', 'recent', 'byowner', 'owners', 'ledger', 'nothumb', 'kinds', 'byday', 'arrivals', 'bymodule', 'bybackend', 'biggest'],
            new FilesWidgets()->catalog()->ids(),
        );
    }

    /**
     * @param list<int> $spans
     */
    #[DataProvider('widgets')]
    public function testEveryWidgetIsFiledUnderItsDirectionAtTheWidthTheDesignDrewIt(string $id, string $group, int $cols, array $spans, bool $on): void
    {
        $widget = new FilesWidgets()->catalog()->get($id);

        self::assertSame($group, $widget->group);
        self::assertSame($cols, $widget->cols);
        self::assertSame($spans, $widget->spans);
        self::assertSame($on, $widget->on);
        self::assertNotNull($widget->note, 'a widget nobody can describe is a widget nobody will choose');
    }

    /**
     * @return iterable<string, array{string, string, int, list<int>, bool}>
     */
    public static function widgets(): iterable
    {
        yield 'the four counts' => ['kpis', 'e', 12, [12, 9, 6], true];
        yield 'browse every file' => ['browse', 'a', 12, [12], true];
        yield 'just arrived' => ['recent', 'a', 6, [12, 9, 6], true];
        yield 'the records that hold files' => ['byowner', 'b', 12, [12], false];
        yield 'modules holding files' => ['owners', 'b', 6, [12, 6], false];
        yield 'the file ledger' => ['ledger', 'c', 12, [12], false];
        yield 'waiting for a small picture' => ['nothumb', 'c', 6, [12, 6], true];
        yield 'what these files are' => ['kinds', 'c', 6, [12, 6], false];
        yield 'day by day' => ['byday', 'd', 12, [12], false];
        yield 'what arrives each week' => ['arrivals', 'd', 6, [12, 6], false];
        yield 'space by module' => ['bymodule', 'e', 6, [12, 6], false];
        yield 'where the bytes are' => ['bybackend', 'e', 6, [12, 6], false];
        yield 'the biggest files' => ['biggest', 'e', 6, [12, 6], false];
    }

    public function testTheShippedCompositionIsTheFourWidgetsTheHubOpensWith(): void
    {
        self::assertSame(
            ['kpis' => 12, 'browse' => 12, 'recent' => 6, 'nothumb' => 6],
            new FilesWidgets()->catalog()->defaultLayout(),
            'how much have we got, show me it, is anything stuck',
        );
    }

    /**
     * @param array<string, int> $layout
     */
    #[DataProvider('presets')]
    public function testEachDirectionComposesTheHubFromItsOwnWidgets(string $id, array $layout): void
    {
        $preset = new FilesWidgets()->catalog()->preset($id);

        self::assertNotNull($preset);
        self::assertSame($layout, $preset->layout);
    }

    /**
     * @return iterable<string, array{string, array<string, int>}>
     */
    public static function presets(): iterable
    {
        yield 'a · contact sheet' => ['a', ['browse' => 12, 'recent' => 12]];
        yield 'b · owner first' => ['b', ['byowner' => 12, 'owners' => 6, 'kpis' => 6]];
        yield 'c · the ledger' => ['c', ['ledger' => 12, 'nothumb' => 6, 'kinds' => 6]];
        yield 'd · by the day it was taken' => ['d', ['byday' => 12, 'arrivals' => 6, 'kpis' => 6]];
        yield 'e · where the bytes are' => ['e', ['kpis' => 12, 'bymodule' => 6, 'bybackend' => 6, 'biggest' => 12]];
    }

    /**
     * The library's headed section and the preset that composes it are the same
     * idea seen twice, so the design writes the trade-off line once and both use
     * it. Two copies of a sentence are two chances to drift.
     */
    public function testADirectionSaysTheSameThingAsAGroupAndAsAPreset(): void
    {
        $catalog = new FilesWidgets()->catalog();

        foreach ($catalog->groups() as $group) {
            $preset = $catalog->preset($group->id);
            self::assertNotNull($preset, $group->id);
            self::assertSame($group->label, $preset->label, $group->id);
            self::assertSame($group->description, $preset->description, $group->id);
        }
    }

    public function testTheShippedCompositionIsOfferedAsAPresetOfItsOwn(): void
    {
        $builtins = new FilesWidgets()->catalog()->builtins();

        self::assertSame(
            ['default', 'a', 'b', 'c', 'd', 'e'],
            array_map(static fn ($p): string => $p->id, $builtins),
            'the direction-neutral screen leads, then the five to adopt',
        );
        self::assertSame('The files hub', $builtins[0]->label);
    }

    public function testEveryWidgetShipsAPartial(): void
    {
        foreach (new FilesWidgets()->catalog()->ids() as $id) {
            self::assertFileExists(
                \dirname(__DIR__, 3).'/templates/files/_w_'.$id.'.html.twig',
                \sprintf('the "%s" widget ships a partial', $id),
            );
        }
    }

    /**
     * THE STATIC-TWIN DISCIPLINE, checked rather than merely written down.
     *
     * Each partial's header says which entry of the design's own declaration it
     * is the twin of. A partial that loses that line is a partial somebody can
     * change without knowing there is a second copy of it in the design app.
     */
    public function testEveryPartialCarriesTheStaticTwinNoteInItsHeader(): void
    {
        foreach (new FilesWidgets()->catalog()->ids() as $id) {
            $header = (string) file_get_contents(\dirname(__DIR__, 3).'/templates/files/_w_'.$id.'.html.twig');

            self::assertStringContainsString('STATIC TWIN', $header, $id);
            self::assertStringContainsString('files.widgets.js', $header, $id.' does not name the entry it is the twin of');
            self::assertStringContainsString('`'.$id.'`', $header, $id.' names the wrong entry');
        }
    }
}
