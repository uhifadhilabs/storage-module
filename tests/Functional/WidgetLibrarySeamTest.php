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

namespace UhifadhiLabs\Storage\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Uhifadhi\Model\WidgetDom;
use UhifadhiLabs\Storage\Model\FilesWidgets;

/**
 * /files/widgets hands the HOST's library component the whole contract.
 *
 * This is the bundle's copy of the host's own WidgetLibrarySeamTest, asserting
 * the same things about the same attributes — because "the library works" is not
 * a thing a bundle can test (the component is the host's) but "we handed it
 * everything it asks for, and our URLs are the ones it will call" is exactly
 * what a bundle must not get wrong.
 *
 * The component itself is stubbed down to its contract in
 * tests/Integration/Fixtures/templates/widgets/_library.html.twig, which reads
 * every one of the nine parameters WITHOUT a default — so a parameter this
 * bundle forgets to pass is a Twig error here rather than a blank screen in a
 * host.
 */
final class WidgetLibrarySeamTest extends FilesTestCase
{
    public function testTheLibraryHandsTheComponentTheWholeContract(): void
    {
        $client = $this->ranger(static::createClient());
        $crawler = $client->request('GET', '/files/widgets');

        self::assertResponseIsSuccessful();

        $root = $crawler->filter('['.WidgetDom::ROOT.']');
        self::assertCount(1, $root, '/files/widgets renders no library root');

        $id = WidgetDom::ID_PLACEHOLDER;
        foreach ([
            WidgetDom::SAVE_URL => '/save',
            WidgetDom::RESET_URL => '/reset',
            WidgetDom::PRESET_URL => '/preset/'.$id,
            WidgetDom::PRESET_COPY_URL => '/preset/'.$id.'/copy',
            WidgetDom::PRESETS_URL => '/presets',
            WidgetDom::PRESET_APPLY_URL => '/presets/'.$id.'/apply',
            WidgetDom::PRESET_RENAME_URL => '/presets/'.$id.'/rename',
            WidgetDom::PRESET_DELETE_URL => '/presets/'.$id.'/delete',
        ] as $attribute => $suffix) {
            self::assertSame('/files/widgets'.$suffix, $root->attr($attribute), $attribute);
        }

        self::assertNotSame('', (string) $root->attr(WidgetDom::CSRF_TOKEN), 'the library mints no token');
    }

    public function testTheCatalogueBlobNamesThisSurfaceAndItsWidgets(): void
    {
        $client = $this->ranger(static::createClient());
        $crawler = $client->request('GET', '/files/widgets');

        /** @var array{surface: string, widgets: list<array{id: string}>, groups: list<array{id: string}>, active: array{kind: string, id: string}} $catalog */
        $catalog = json_decode(
            $crawler->filter('script['.WidgetDom::CATALOG.']')->text(),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );

        self::assertSame(FilesWidgets::SURFACE, $catalog['surface']);
        self::assertSame(
            ['kpis', 'browse', 'recent', 'byowner', 'owners', 'ledger', 'nothumb', 'kinds', 'byday', 'arrivals', 'bymodule', 'bybackend', 'biggest'],
            array_column($catalog['widgets'], 'id'),
        );
        self::assertSame(['a', 'b', 'c', 'd', 'e'], array_column($catalog['groups'], 'id'), 'the five directions the hub was drawn in');
        self::assertContains($catalog['active']['kind'], ['design', 'mine']);
    }

    public function testEveryWidgetIsPreviewedAsTheRealThing(): void
    {
        $client = $this->ranger(static::createClient());
        $crawler = $client->request('GET', '/files/widgets');

        $templates = $crawler->filter('['.WidgetDom::TEMPLATE.']');

        self::assertCount(13, $templates, 'one preview per widget in the catalogue');
        self::assertSame(
            array_map(static fn (object $w): string => $w->id, FilesWidgets::widgets()),
            $templates->each(static fn ($n): string => (string) $n->attr(WidgetDom::TEMPLATE)),
        );
    }

    public function testThePreviewIsTheWidget(): void
    {
        $client = $this->ranger(static::createClient());
        $crawler = $client->request('GET', '/files/widgets');
        $library = $crawler->filter('['.WidgetDom::TEMPLATE.'=browse]')->html();
        $hub = $client->request('GET', '/files')->filter('[data-w=browse]')->html();

        self::assertStringContainsString('FL·01', $library, 'the preview carries the design index the hub carries');
        self::assertStringContainsString('f-filters', $library, 'and the filter row, because it IS the widget');
        self::assertStringContainsString('FL·01', $hub);
    }

    public function testTheLibraryDrawsTheLandmarksTheComponentOwns(): void
    {
        $client = $this->ranger(static::createClient());
        $crawler = $client->request('GET', '/files/widgets');

        self::assertCount(1, $crawler->filter('.w-canvas'));
        self::assertCount(1, $crawler->filter('[data-picker]'));
        self::assertCount(1, $crawler->filter('.w-previewbar'));
        self::assertCount(1, $crawler->filter('.w-preset-active'), 'exactly one preset is the active one');
        self::assertCount(1, $crawler->filter('['.WidgetDom::RESET.']'), 'the reset control lives on the page, not in the component');
    }

    public function testResettingAsksFirstThroughTheHostsOwnDialog(): void
    {
        $client = $this->ranger(static::createClient());
        $crawler = $client->request('GET', '/files/widgets');
        $reset = $crawler->filter('['.WidgetDom::RESET.']');

        self::assertSame('confirm-modal', $reset->attr('data-controller'), 'the bundle states WHAT to ask; the host owns the dialog');
        self::assertNotSame('', (string) $reset->attr('data-confirm-modal-title-value'));
    }

    #[DataProvider('writes')]
    public function testEveryWriteRefusesWithoutTheHostsToken(string $method, string $path): void
    {
        $client = $this->ranger(static::createClient());
        $client->request($method, $path);

        self::assertResponseStatusCodeSame(403, $path.' accepted a write with no token');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function writes(): iterable
    {
        $id = WidgetDom::ID_PLACEHOLDER;

        yield 'save' => ['POST', '/files/widgets/save'];
        yield 'reset' => ['POST', '/files/widgets/reset'];
        yield 'apply a built-in' => ['POST', '/files/widgets/preset/c'];
        yield 'copy a built-in' => ['POST', '/files/widgets/preset/c/copy'];
        yield 'create one of my own' => ['POST', '/files/widgets/presets'];
        yield 'apply one of my own' => ['POST', '/files/widgets/presets/'.$id.'/apply'];
        yield 'rename one of my own' => ['POST', '/files/widgets/presets/'.$id.'/rename'];
        yield 'delete one of my own' => ['POST', '/files/widgets/presets/'.$id.'/delete'];
    }

    public function testALayoutIsSavedAndReadBackOnTheHub(): void
    {
        // One kernel across the three requests: the host stores a layout in its
        // own database, and the double that stands in for it here stores it in
        // memory — which only survives if the kernel does.
        $client = $this->ranger(static::createClient());
        $client->disableReboot();
        $crawler = $client->request('GET', '/files/widgets');
        $token = (string) $crawler->filter('['.WidgetDom::ROOT.']')->attr(WidgetDom::CSRF_TOKEN);

        $client->request(
            'POST',
            '/files/widgets/save',
            server: ['HTTP_X-CSRF-Token' => $token, 'CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'order' => ['ledger', 'kpis'],
                'widgets' => ['ledger' => ['on' => true, 'cols' => 12], 'kpis' => ['on' => false, 'cols' => 12]],
            ], flags: \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(204);

        $hub = $client->request('GET', '/files');
        $drawn = $hub->filter('.w-grid > .w-cell[data-widget-id]')->each(static fn ($n): string => (string) $n->attr('data-widget-id'));

        self::assertSame('ledger', $drawn[0], 'the person’s own order leads');
        self::assertNotContains('kpis', $drawn, 'a widget switched off is simply absent');
    }

    public function testAnUnreadableLayoutIsRefusedRatherThanStored(): void
    {
        $client = $this->ranger(static::createClient());
        $crawler = $client->request('GET', '/files/widgets');
        $token = (string) $crawler->filter('['.WidgetDom::ROOT.']')->attr(WidgetDom::CSRF_TOKEN);

        $client->request(
            'POST',
            '/files/widgets/save',
            server: ['HTTP_X-CSRF-Token' => $token, 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['order' => ['no-such-widget']], flags: \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testAdoptingADirectionComposesTheHubFromIt(): void
    {
        $client = $this->ranger(static::createClient());
        $client->disableReboot();
        $crawler = $client->request('GET', '/files/widgets');
        $token = (string) $crawler->filter('['.WidgetDom::ROOT.']')->attr(WidgetDom::CSRF_TOKEN);

        $client->request('POST', '/files/widgets/preset/c', server: ['HTTP_X-CSRF-Token' => $token]);
        self::assertResponseStatusCodeSame(204);

        $drawn = $client->request('GET', '/files')
            ->filter('.w-grid > .w-cell[data-widget-id]')
            ->each(static fn ($n): string => (string) $n->attr('data-widget-id'));

        self::assertSame(['ledger', 'nothumb', 'kinds'], $drawn, 'direction C is the ledger, the queue and the kinds — and nothing else');
    }

    public function testAnUnknownDirectionIsANotFound(): void
    {
        $client = $this->ranger(static::createClient());
        $crawler = $client->request('GET', '/files/widgets');
        $token = (string) $crawler->filter('['.WidgetDom::ROOT.']')->attr(WidgetDom::CSRF_TOKEN);

        $client->request('POST', '/files/widgets/preset/zzz', server: ['HTTP_X-CSRF-Token' => $token]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheLibraryIsNotOpenToAStranger(): void
    {
        static::createClient()->request('GET', '/files/widgets');

        self::assertResponseStatusCodeSame(403);
    }
}
