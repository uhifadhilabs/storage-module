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
 * Where files go — /files/settings.
 *
 * The whole page is READ-ONLY TRUTH FROM CONFIGURATION, so every assertion below
 * is against what the test kernel actually configured: the local adapter, the
 * default allowlist, the default size cap and the default thumbnail edge. A line
 * on this page that is not a fact about this deployment is a bug, not a sample.
 */
final class FilesSettingsPageTest extends FilesTestCase
{
    public function testOnlyAnAdministratorOpensIt(): void
    {
        $client = $this->warden(static::createClient());
        $client->request('GET', '/files/settings');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.pg', 'Where files go');
    }

    public function testSomebodySignedInButNotAnAdministratorIsRefused(): void
    {
        $client = $this->ranger(static::createClient());
        $client->request('GET', '/files/settings');

        self::assertResponseStatusCodeSame(403, 'seeing where files are kept is seeing something about every file at once');
    }

    public function testAStrangerIsRefusedToo(): void
    {
        static::createClient()->request('GET', '/files/settings');

        self::assertResponseStatusCodeSame(403);
    }

    public function testItNamesThePlaceThisDeploymentActuallyConfigured(): void
    {
        $client = $this->warden(static::createClient());
        $crawler = $client->request('GET', '/files/settings');

        $stores = $crawler->filter('.f-store');
        self::assertCount(1, $stores, 'this bundle declares exactly one named storage, and saying so plainly beats an invented second card');
        // The test kernel configures the local adapter and names nothing, so the
        // hub falls back to a description rather than to a vendor.
        self::assertStringContainsString('This server', $stores->filter('.f-be')->text());
        self::assertSame('local', 'f-be local' === trim((string) $stores->filter('.f-be')->attr('class')) ? 'local' : 'other');
    }

    public function testTheModuleToStorageMapIsDrawnFromTheInstalledSources(): void
    {
        $client = $this->warden(static::createClient());
        $crawler = $client->request('GET', '/files/settings');

        $map = $crawler->filter('.f-map .cellL');
        self::assertCount(1, $map, 'one line per module that publishes files');
        self::assertStringContainsString('Fieldwork', $map->text());
        self::assertStringContainsString('a record’s photographs', $map->text(), 'in the module’s own words');
    }

    public function testWhatIsAllowedInIsTheDeploymentsOwnAllowlist(): void
    {
        $client = $this->warden(static::createClient());
        $crawler = $client->request('GET', '/files/settings');

        $allowed = $crawler->filter('.c')->last()->text().$crawler->text();

        self::assertStringContainsString('jpeg', $allowed);
        self::assertStringContainsString('by reading the file, not its name', $allowed, 'the allowlist is on the DETECTED type');
        self::assertStringContainsString('12.6 MB', $allowed, 'the configured size cap, in the words a person reads');
        self::assertStringContainsString('~400px', $allowed, 'the configured thumbnail edge');
    }

    public function testThePromisesAreStatedAsPromises(): void
    {
        $client = $this->warden(static::createClient());
        $crawler = $client->request('GET', '/files/settings');
        $text = $crawler->text();

        self::assertStringContainsString('Make a file public', $text);
        self::assertStringContainsString('never — the record decides', $text);
        self::assertStringContainsString('outside the web root', $crawler->filter('.f-store .use')->text().$text);
    }

    public function testItOffersNoWayToChangeAnything(): void
    {
        $client = $this->warden(static::createClient());
        $crawler = $client->request('GET', '/files/settings');

        self::assertCount(0, $crawler->filter('form'), 'where the bytes live is a deployment decision, not a form');
        self::assertCount(0, $crawler->filter('input'));
    }
}
