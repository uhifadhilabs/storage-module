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

namespace Uhifadhi\Storage\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Storage\Enum\FileKindEnum;
use Uhifadhi\Storage\Model\FileEntry;
use Uhifadhi\Storage\Model\FileFilter;

/**
 * THE FILTER ROW IS ONE QUERY, and this is that query as a value.
 *
 * Every chip on the hub — the four dropdowns and the two pill sets — reads and
 * writes this object, so the row can be drawn as plain links: each option is
 * this filter with ONE key replaced, rendered back out as a query string.
 */
#[CoversClass(FileFilter::class)]
final class FileFilterTest extends TestCase
{
    public function testTheEightParametersAreReadOffTheQuery(): void
    {
        $filter = FileFilter::fromQuery([
            'module' => 'patrols',
            'area' => 'north-block',
            'kind' => 'photo',
            'day' => '2026-08-21',
            'backend' => 'evidence',
            'thumb' => 'made',
            'q' => 'OBS-0214',
            'view' => 'list',
        ]);

        self::assertSame('patrols', $filter->module);
        self::assertSame('north-block', $filter->area);
        self::assertSame(FileKindEnum::Photo, $filter->kind);
        self::assertSame('2026-08-21', $filter->day);
        self::assertSame('evidence', $filter->backend);
        self::assertSame(FileFilter::THUMB_MADE, $filter->thumb);
        self::assertSame('OBS-0214', $filter->q);
        self::assertSame(FileFilter::VIEW_LIST, $filter->view);
    }

    public function testAShapeNobodyCanDrawFallsBackToTheThumbnails(): void
    {
        self::assertSame(FileFilter::VIEW_GRID, FileFilter::fromQuery(['view' => 'carousel'])->view);
        self::assertSame(FileFilter::VIEW_GRID, FileFilter::fromQuery([])->view);
    }

    public function testTheQueryLeavesOutEveryChoiceNobodyMade(): void
    {
        self::assertSame([], new FileFilter()->toQuery());
        self::assertSame(
            ['area' => 'south-block', 'view' => 'list'],
            new FileFilter(area: 'south-block', view: FileFilter::VIEW_LIST)->toQuery(),
        );
    }

    public function testChoosingOneOptionReplacesOneKeyAndKeepsTheRest(): void
    {
        $filter = FileFilter::fromQuery(['area' => 'south-block', 'view' => 'list', 'q' => 'REC']);

        self::assertSame(
            ['area' => 'south-block', 'backend' => 'evidence', 'q' => 'REC', 'view' => 'list'],
            $filter->withBackend('evidence')->toQuery(),
        );
        self::assertSame(
            ['q' => 'REC', 'view' => 'list'],
            $filter->withArea(null)->toQuery(),
            'the "every area" option is this filter with the area let go of',
        );
    }

    public function testTheShapeIsNotANarrowing(): void
    {
        self::assertTrue(FileFilter::fromQuery(['view' => 'list'])->isEmpty());
        self::assertFalse(FileFilter::fromQuery(['backend' => 'evidence'])->isEmpty());
    }

    public function testWhereTheBytesAreIsAnsweredAboutTheFileRatherThanByIt(): void
    {
        // A FILE DOES NOT KNOW WHERE ITS BYTES ARE — the installation's
        // configuration does, so the place is handed in beside the file.
        $filter = FileFilter::fromQuery(['backend' => 'evidence']);

        self::assertTrue($filter->keeps(self::file(), 'evidence'));
        self::assertFalse($filter->keeps(self::file(), 'somewhere-else'));
        self::assertFalse($filter->keeps(self::file(), null));
        self::assertTrue(new FileFilter()->keeps(self::file(), null), 'with no choice made, nowhere is wrong');
    }

    private static function file(): FileEntry
    {
        return new FileEntry(
            'fieldwork/rec-0001/a.jpg',
            'IMG_1204.jpg',
            'image/jpeg',
            1000,
            'REC-0001',
            null,
            'fieldwork',
            'Fieldwork',
        );
    }
}
