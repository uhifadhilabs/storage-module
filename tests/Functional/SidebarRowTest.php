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

/**
 * THE FILES ROW IN THE SHELL'S SIDEBAR.
 *
 * This used to be documentation: "open the application's layout.html.twig, type
 * a nav-item beside the others, then remember a second edit in a Twig extension
 * so the row lights up". Two hand-edits in somebody else's repository, which no
 * test could see and every installation had to redo. It is a service tagged into
 * the shell's nav seam now, so it is a thing that can be asserted — and these
 * are the assertions.
 */
final class SidebarRowTest extends FilesTestCase
{
    public function testSomebodySignedInIsOfferedTheHubInTheSidebar(): void
    {
        $client = $this->ranger(static::createClient());
        $crawler = $client->request('GET', '/files');

        $row = $crawler->filter('a[href="/files"]')->reduce(
            static fn ($node): bool => str_contains($node->text(), 'Files'),
        );

        self::assertGreaterThan(0, $row->count(), 'the shell offers no Files row');
    }

    /**
     * ABSENT, NEVER HIDDEN. A row a stranger can read in the HTML tells them the
     * organisation has files, which is the organisation's business.
     */
    public function testAStrangerIsOfferedNothing(): void
    {
        $client = static::createClient();
        $client->request('GET', '/files');

        self::assertStringNotContainsString('>Files<', (string) $client->getResponse()->getContent());
    }
}
