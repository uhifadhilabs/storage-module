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

namespace Uhifadhi\Storage\Tests\Functional;

use Symfony\Component\DomCrawler\Crawler;
use Twig\Environment;
use Uhifadhi\Storage\Enum\ThumbStateEnum;

/**
 * THE KEPT EVIDENCE TILE — the second piece other modules build on.
 *
 * A file already attached and a file that arrived a second ago must be the same
 * box, or a record's card shows two kinds of tile for the same thing. The
 * upload component's finished state is therefore drawn from ONE macro, here, and
 * a consuming module calls it instead of retyping the markup — which is what an
 * incident case file and a patrol log did, each slightly differently, until this
 * existed.
 *
 * FOUR STATES, IN THE STORAGE'S OWN WORDS, and the tile says which without
 * changing its box:
 *
 *   made   the picture fills the tile and the tile opens the file's page
 *   wait   the camera glyph and a pill saying the thumbnail is being made
 *   failed the same glyph and a pill saying none could be made
 *   none   a document: its own glyph and ground, and no pill — there was never
 *          a picture to make
 *
 * A tile with nothing to look at is deliberately NOT openable, and the pill's
 * word is {@see ThumbStateEnum::label()} rather than a string typed here, so the
 * vocabulary cannot drift between the hub, this tile and the fresh one the
 * controller draws after an upload.
 */
final class EvidenceTileComponentTest extends FilesTestCase
{
    private const string PARTIAL = '@UhifadhiStorage/upload/_tile.html.twig';

    private ?Environment $twig = null;

    /** Every state is the same box: the grid must not jump as a thumbnail lands. */
    public function testEveryStateKeepsTheOneBoxTheGridWasBuiltFor(): void
    {
        foreach (['made', 'wait', 'failed', 'none'] as $state) {
            $tile = $this->tile(['name' => 'IMG_1204.jpg', 'thumbState' => $state, 'thumbUrl' => '/files/e/thumb.jpg']);

            self::assertCount(
                1,
                $tile->filter('.upl-tile.done'),
                \sprintf('a "%s" tile is still an ordinary kept evidence tile', $state),
            );
            self::assertCount(1, $tile->filter('.fn'), 'every state prints what the file is called');
        }
    }

    /**
     * A made thumbnail is the point of the change: the picture the storage
     * already makes fills the tile, drawn in the same `.sh` shell the hub uses,
     * and the tile opens the file.
     */
    public function testAMadeThumbnailFillsTheTileAndOpensTheFile(): void
    {
        $tile = $this->tile([
            'name' => 'IMG_1204.jpg',
            'label' => 'IMG_1204.jpg · 11:02',
            'key' => 'photo/obs-214/img-1204.jpg',
            'thumbState' => 'made',
            'thumbUrl' => '/files/e/photo/obs-214/img-1204.thumb.jpg',
            'detailUrl' => '/files/f/photo/obs-214/img-1204.jpg',
        ]);

        self::assertCount(1, $tile->filter('.upl-tile.done.shot'), 'a picture gets the flat photo ground');
        $picture = $tile->filter('a.sh');
        self::assertCount(1, $picture, 'the picture layer is the link, because the removal may not sit inside it');
        self::assertSame('/files/f/photo/obs-214/img-1204.jpg', $picture->attr('href'));

        $image = $picture->filter('img');
        self::assertCount(1, $image);
        self::assertSame('/files/e/photo/obs-214/img-1204.thumb.jpg', $image->attr('src'));
        self::assertSame('IMG_1204.jpg', $image->attr('alt'), 'the alt is the file, not the caption under it');
        self::assertSame('lazy', $image->attr('loading'), 'a grid of evidence must not fetch every picture at once');

        self::assertCount(0, $tile->filter('.th'), 'there is nothing to explain when the picture is there');
        self::assertSame('IMG_1204.jpg · 11:02', $tile->filter('.fn')->text());
    }

    /**
     * Queued, and saying so. The word is the enum's, and the tile does not
     * pretend to be openable when there is nothing yet to open.
     */
    public function testAQueuedThumbnailSaysSoAndIsNotOpenable(): void
    {
        $tile = $this->tile([
            'name' => 'IMG_1206.jpg',
            'key' => 'photo/obs-214/img-1206.jpg',
            'thumbState' => 'wait',
            'detailUrl' => '/files/f/photo/obs-214/img-1206.jpg',
        ]);

        self::assertCount(1, $tile->filter('.upl-tile.done.making'));
        self::assertSame(ThumbStateEnum::Waiting->label(), $tile->filter('.th')->text());
        self::assertCount(0, $tile->filter('.sh'), 'there is no picture to shell');
        self::assertCount(0, $tile->filter('a'), 'only a made thumbnail links to the file');
    }

    /**
     * Could not be decoded. The file is kept, the original is untouched, and the
     * tile says which of the two "no picture" facts this is.
     */
    public function testAThumbnailThatCouldNotBeMadeSaysThatInsteadOfMaking(): void
    {
        $tile = $this->tile([
            'name' => 'IMG_1207.HEIC',
            'key' => 'photo/obs-214/img-1207.heic',
            'thumbState' => 'failed',
            'detailUrl' => '/files/f/photo/obs-214/img-1207.heic',
        ]);

        self::assertCount(1, $tile->filter('.upl-tile.done.nothumb'));
        self::assertSame(ThumbStateEnum::Failed->label(), $tile->filter('.th')->text());
        self::assertCount(0, $tile->filter('a'), 'only a made thumbnail links to the file');
    }

    /**
     * A document is not a photograph that failed: there was never a picture to
     * make, so it keeps the document ground and carries no pill at all.
     */
    public function testADocumentKeepsItsOwnGroundAndCarriesNoPill(): void
    {
        $tile = $this->tile([
            'name' => 'claim_form_signed.pdf',
            'key' => 'document/inc-313/claim.pdf',
            'thumbState' => 'none',
        ]);

        self::assertCount(1, $tile->filter('.upl-tile.done.doc'));
        self::assertCount(0, $tile->filter('.th'), 'a document is not waiting for anything');
        self::assertCount(0, $tile->filter('.shot, .making, .nothumb'));
    }

    /** The removal is drawn for a tile that can be removed, and only then. */
    public function testTheRemovalIsDrawnOnlyWhereThereIsAKeyToRemove(): void
    {
        $removable = $this->tile([
            'name' => 'IMG_1204.jpg',
            'key' => 'photo/obs-214/img-1204.jpg',
            'thumbState' => 'made',
            'thumbUrl' => '/files/e/thumb.jpg',
        ]);

        $button = $removable->filter('button.rm');
        self::assertCount(1, $button);
        self::assertSame('photo/obs-214/img-1204.jpg', $button->attr('data-upl-key'));
        self::assertNotNull($button->attr('data-upl-remove'), 'the controller listens for this, not for a class');

        self::assertCount(
            0,
            $this->tile(['name' => 'IMG_1204.jpg', 'thumbState' => 'made', 'thumbUrl' => '/files/e/thumb.jpg'])->filter('button.rm'),
            'a listing nobody may edit offers no removal',
        );
    }

    /**
     * A tile nobody may remove from is a link all over, which is what a
     * read-only listing of recent evidence wants: the whole cell opens the file.
     */
    public function testAReadOnlyTileIsTheLinkItself(): void
    {
        $tile = $this->tile([
            'name' => 'IMG_1204.jpg',
            'thumbState' => 'made',
            'thumbUrl' => '/files/e/thumb.jpg',
            'detailUrl' => '/files/f/photo/obs-214/img-1204.jpg',
        ]);

        $whole = $tile->filter('a.upl-tile.done.shot');
        self::assertCount(1, $whole, 'with no removal in the way, the cell itself is the link');
        self::assertSame('/files/f/photo/obs-214/img-1204.jpg', $whole->attr('href'));
        self::assertCount(1, $whole->filter('span.sh img'), 'the picture keeps its shell inside the link');
    }

    /** @param array<string, string> $file */
    private function tile(array $file): Crawler
    {
        $keys = implode(', ', array_map(
            static fn (string $key): string => \sprintf('%s: file.%s', $key, $key),
            array_keys($file),
        ));

        return new Crawler($this->twig()
            ->createTemplate('{% import "'.self::PARTIAL.'" as evidence %}{{ evidence.kept({'.$keys.'}) }}')
            ->render(['file' => $file]));
    }

    /** One kernel per test, however many tiles the test draws. */
    private function twig(): Environment
    {
        if (null === $this->twig) {
            static::createClient();
            $twig = static::getContainer()->get('twig');
            self::assertInstanceOf(Environment::class, $twig);
            $this->twig = $twig;
        }

        return $this->twig;
    }
}
