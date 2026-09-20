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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;

/**
 * FILES WEARS THE AREA IDIOM, and this is the test of the frame rather than of
 * any one screen: the same header on every tab, one line per tab under it, a
 * strip of four with exactly one lit, one Configure action at the right-hand
 * end, and the section's own subtree in the sidebar.
 *
 * NONE OF IT IS TYPED BY THIS BUNDLE. The strip, the header sockets and the
 * Configure entry are the shell's frame, resolved from the surface marker each
 * route carries — so what is asserted here is that the marker is on, the tabs
 * are declared, and the sections are declared. A screen that forgot the marker
 * renders an AREA's strip instead, which is exactly the failure worth catching.
 */
final class FilesSectionFrameTest extends FilesTestCase
{
    /** The four data tabs, in the order the strip draws them. */
    private const array TABS = ['Overview', 'Files', 'Sources', 'Storage'];

    /**
     * THE FOUR SCREENS AND THE TAB EACH OF THEM LIGHTS.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function tabsWithLabel(): iterable
    {
        yield 'overview' => ['/files/overview', 'Overview'];
        yield 'register' => ['/files', 'Files'];
        yield 'sources' => ['/files/sources', 'Sources'];
        yield 'storage' => ['/files/storage', 'Storage'];
    }

    /**
     * The same four, for the rules that hold whatever the lit tab is.
     *
     * @return iterable<string, array{string}>
     */
    public static function tabs(): iterable
    {
        foreach (self::tabsWithLabel() as $name => [$path]) {
            yield $name => [$path];
        }
    }

    /**
     * THE HEADER IS THE SECTION'S ON EVERY TAB. The title says where you are in
     * the product; the strip says which screen. A title that changed per tab
     * would make four screens read as four places.
     */
    #[DataProvider('tabs')]
    public function testTheHeaderIsTheSectionsOnEveryTab(string $path): void
    {
        $crawler = $this->open($path);

        self::assertSame('Files', trim($crawler->filter('h1.pg')->text()));
    }

    /** ONE LINE PER TAB, and it is this tab's rather than the section's. */
    #[DataProvider('tabs')]
    public function testEveryTabCarriesItsOwnSubline(string $path): void
    {
        $crawler = $this->open($path);

        self::assertCount(1, $crawler->filter('.pghead .pgsub'));
        self::assertNotSame('', trim($crawler->filter('.pghead .pgsub')->text()));
    }

    /**
     * FOUR TABS, EVERY ONE A LIVE LINK, exactly one lit — and the lit one is
     * the screen being looked at.
     */
    #[DataProvider('tabsWithLabel')]
    public function testTheStripIsFourLiveTabsWithThisOneLit(string $path, string $label): void
    {
        $crawler = $this->open($path);

        $tabs = $crawler->filter('.atabs a');
        self::assertSame(self::TABS, $tabs->each(static fn (Crawler $a): string => trim($a->text())));

        foreach ($tabs->each(static fn (Crawler $a): string => (string) $a->attr('href')) as $href) {
            self::assertNotSame('', $href, 'a tab the viewer may not have is withheld, never rendered without a destination');
            self::assertStringStartsWith('/files', $href);
        }

        self::assertCount(1, $crawler->filter('.atabs a.on'));
        self::assertSame($label, trim($crawler->filter('.atabs a.on')->text()));
    }

    /**
     * ONE CONFIGURE ACTION, ON EVERY TAB, ALWAYS LAST IN THE ROW. The frame
     * writes it; this bundle never types one, which is the only way "exactly
     * one entry per surface" stays true.
     */
    #[DataProvider('tabs')]
    public function testConfigureIsTheLastActionOnEveryTab(string $path): void
    {
        $crawler = $this->open($path);

        $actions = $crawler->filter('.pghead .pgact > a');
        self::assertGreaterThan(0, $actions->count());
        self::assertStringContainsString('Configure', trim($actions->last()->text()));
        self::assertSame(1, substr_count($crawler->filter('.pghead .pgact')->html(), 'Configure'));
    }

    /**
     * THE SECTION'S SUBTREE, in the sidebar, wherever you are inside it — the
     * same four screens in the same order, because the tree and the strip are
     * two readings of one list.
     */
    #[DataProvider('tabsWithLabel')]
    public function testTheSidebarOpensTheSectionsOwnScreens(string $path, string $label): void
    {
        $crawler = $this->open($path);

        $row = $crawler->filter('.nav .nav-item')->reduce(
            static fn (Crawler $item): bool => str_contains($item->text(), 'Files'),
        );
        self::assertGreaterThan(0, $row->count(), 'the section has a row');

        $children = $crawler->filter('.nav .ntree a');
        self::assertSame(self::TABS, $children->each(static fn (Crawler $a): string => trim($a->text())));

        $lit = $children->reduce(static fn (Crawler $a): bool => str_contains((string) $a->attr('class'), 'on'));
        self::assertCount(1, $lit, 'exactly one screen in the subtree is lit');
        self::assertSame($label, trim($lit->text()));
    }

    /**
     * A SCREEN INSIDE THE SECTION THAT IS NOT ONE OF ITS PLACES GETS NO STRIP.
     * A file's own page is headed by the file; drawing the section's strip
     * there would say the file was one of the section's four screens, and
     * drawing an AREA's would claim a place the hub is not in.
     */
    public function testAFilesOwnPageDrawsNoStrip(): void
    {
        $crawler = $this->open('/files/f/fieldwork/rec-0001/a.jpg');

        self::assertCount(0, $crawler->filter('.atabs'));
    }

    /**
     * THE CONFIGURE SECTIONS STAND WHERE THE DATA TABS STAND. One strip, one
     * component, one position — and the four entries never repeat a tab word,
     * which is why the two in the middle are "Storage targets" and "Source
     * modules" rather than "Storage" and "Sources".
     */
    public function testAConfigureSectionWearsTheSectionStripInstead(): void
    {
        $client = $this->warden(static::createClient());
        $crawler = $client->request('GET', '/files/configure');
        self::assertResponseIsSuccessful();

        self::assertSame(
            ['Widget library', 'Storage targets', 'Source modules', 'Files settings'],
            $crawler->filter('.atabs a')->each(static fn (Crawler $a): string => trim($a->text())),
        );
        self::assertSame('Files settings', trim($crawler->filter('.atabs a.on')->text()));
    }

    /** The one Configure action LIGHTS on a configure screen and is the way back. */
    public function testConfigureLightsOnAConfigureScreen(): void
    {
        $client = $this->warden(static::createClient());
        $crawler = $client->request('GET', '/files/configure/sources');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.pghead .pgact a.tgl.on'));
    }

    private function open(string $path): Crawler
    {
        $client = $this->warden(static::createClient());
        $crawler = $client->request('GET', $path);

        self::assertResponseIsSuccessful();

        return $crawler;
    }
}
