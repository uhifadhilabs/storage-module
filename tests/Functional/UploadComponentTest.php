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

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DomCrawler\Crawler;
use Uhifadhi\Storage\Twig\UploadExtension;
use Uhifadhi\Storage\Twig\UploadRuntime;

/**
 * WHAT ONE TWIG LINE DRAWS.
 *
 * A module gains uploads by implementing one interface and writing
 * `{{ render_upload(...) }}`; everything asserted here is what it gets for that,
 * rendered by a stand-in host page that writes exactly that line and nothing
 * else.
 *
 * THE IDLE STATE IS THE ONLY ONE THAT IS MARKUP. Every other moment — the queue,
 * the progress, the refusal, the finished chip, the removal question — is about
 * a file that did not exist when the page was rendered, and is drawn by the
 * controller. What keeps THOSE faithful to the design is
 * {@see \Uhifadhi\Storage\Tests\Unit\Template\UploadSeamTest}, which reads the
 * shipped asset as text.
 */
#[CoversClass(UploadExtension::class)]
#[CoversClass(UploadRuntime::class)]
final class UploadComponentTest extends FilesTestCase
{
    public function testTheDropzoneCardIsWaiting(): void
    {
        $zone = $this->page()->filter('[data-stub=zone] .upl-zone');

        self::assertCount(1, $zone);
        self::assertStringContainsString('Drop files here, or', $zone->text());
        self::assertCount(1, $zone->filter('a[data-upl-browse]'), 'the picker door is a link, as the design draws it');
        self::assertCount(1, $zone->filter('svg'), 'the upload mark');
    }

    /**
     * The design's instruction, in its own words: the allowed kinds and the size
     * limit are stated up front, "never discovered by being refused".
     */
    public function testTheZoneStatesTheRuleUpFront(): void
    {
        $kinds = $this->page()->filter('[data-stub=zone] .upl-kinds')->text();

        self::assertStringContainsString('jpg · png · heic', $kinds);
        self::assertStringContainsString('up to 12.6 MB each', $kinds);
        self::assertStringContainsString('10 files at a time', $kinds);
    }

    public function testTheQueueIsThereAndEmptyRatherThanAbsent(): void
    {
        $list = $this->page()->filter('[data-stub=zone] .upl-list');

        self::assertCount(1, $list);
        self::assertNotNull($list->attr('hidden'), 'an empty bordered box is not a state the design has');
    }

    public function testTheAddTileIsOneCellOfTheEvidenceGrid(): void
    {
        $tile = $this->page()->filter('[data-stub=tile] .upl-tile');

        self::assertCount(1, $tile);
        self::assertSame('upl-tile idle', $tile->attr('class'), 'dashed, and the same box as its neighbours');
        self::assertSame('button', $tile->nodeName(), 'interactive chrome is a real button, so the keyboard reaches it');
        self::assertSame('Add evidence', $tile->filter('.lbl')->text());
    }

    public function testTheAddTileSitsBesideTheTilesTheModuleDrewItself(): void
    {
        $grid = $this->page()->filter('[data-stub=tile]');

        self::assertCount(1, $grid->filter('.i-ph'), "the module's own kept tile");
        self::assertCount(1, $grid->filter('.i-ph .rm[data-upl-remove]'), 'which gains a working remove for free');
    }

    /** Both presentations carry the same wiring — one behaviour, two presentations. */
    public function testBothPresentationsCarryTheControllerAndTheEndpoint(): void
    {
        foreach (['[data-stub=zone] .upl-zone', '[data-stub=tile] .upl-tile'] as $selector) {
            $node = $this->page()->filter($selector);

            self::assertSame('uhifadhi--storage-module--upload', $node->attr('data-controller'), $selector);
            self::assertSame('/files/upload', $node->attr('data-upl-upload-url'), $selector);
            self::assertSame('/files/__key__', $node->attr('data-upl-remove-url'), $selector);
            self::assertSame('stub:open', $node->attr('data-upl-target'), $selector);
            self::assertSame('10', $node->attr('data-upl-max-files'), $selector);
            self::assertNotSame('', (string) $node->attr('data-upl-token'), $selector);
        }
    }

    /** The picker's filter is the target's own allowlist, not a typed list. */
    public function testThePickerIsFilteredByWhatTheTargetTakes(): void
    {
        self::assertSame(
            'image/jpeg,image/png,image/heic,image/heif,image/webp',
            $this->page()->filter('[data-stub=zone] .upl-zone')->attr('data-upl-accept'),
        );
    }

    /**
     * A target no module answers for gets NO component. Not a broken box and not
     * an error: the endpoint would refuse it anyway, and a door that cannot open
     * is worse than no door.
     */
    public function testATargetNobodyAnswersForDrawsNothing(): void
    {
        self::assertSame('', trim($this->page()->filter('[data-stub=unknown]')->html()));
    }

    /** The classic-form door, for a page that posts rather than listens. */
    public function testAPageMayNameAHiddenInputForTheKey(): void
    {
        self::assertSame(
            'evidence[]',
            $this->page()->filter('[data-stub=form] .upl-zone')->attr('data-upl-input-name'),
        );
    }

    /** NOTHING OF THE MODULE'S IS ASKED FOR. No stylesheet, no script, no route. */
    public function testTheComponentDefinesNoClassOfItsOwn(): void
    {
        $html = $this->page()->filter('body')->html();

        self::assertStringNotContainsString('<style', $html);
        self::assertStringNotContainsString('style="', $html, 'no inline styles: every rule is the shell’s .upl-*');
    }

    private ?Crawler $page = null;

    /** One render, read many times: booting a second client per assertion is not supported. */
    private function page(): Crawler
    {
        return $this->page ??= $this->ranger(self::createClient())->request('GET', '/stub/upload/stub:open');
    }
}
