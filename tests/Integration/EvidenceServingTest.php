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

namespace Uhifadhi\Storage\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Storage\Service\EvidenceStorage;

/**
 * Authenticated serving. Field photographs are evidence — a snare, a carcass,
 * sometimes a person — so the route that hands them back is exercised the way
 * an attacker would: signed out, signed in but unentitled, and aimed at a key
 * whose owning module was never installed.
 */
final class EvidenceServingTest extends WebTestCase
{
    use \Uhifadhi\Storage\Tests\RealPeopleTrait;

    private const string IMAGES = __DIR__.'/../Fixtures/images';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::buildSchema();
    }

    private function storeUnder(string $prefix, string $clientKey = 'k'): string
    {
        /** @var EvidenceStorage $storage */
        $storage = self::getContainer()->get('test_public.'.EvidenceStorage::class);

        $copy = sys_get_temp_dir().'/storage-module-tests/'.bin2hex(random_bytes(6)).'.jpg';
        @mkdir(\dirname($copy), 0o775, true);
        copy(self::IMAGES.'/landscape-800x600.jpg', $copy);

        return $storage->store(new UploadedFile($copy, 'photo.jpg', 'image/jpeg', test: true), $prefix, $clientKey)->key;
    }

    private function signIn(): void
    {
        $this->client->loginUser(self::rangerAccount());
    }

    public function testAGrantingVoterLetsASignedInUserSeeThePhotograph(): void
    {
        $key = $this->storeUnder('granted/obs-1');
        $this->signIn();

        $this->client->request('GET', '/storage/evidence/'.$key);

        self::assertResponseIsSuccessful();
        self::assertSame(file_get_contents(self::IMAGES.'/landscape-800x600.jpg'), $this->streamedContent());
    }

    /**
     * A StreamedResponse holds no body of its own — it writes to the output
     * buffer when sent — so Response::getContent() is false by design.
     *
     * The bytes are read from the BROWSERKIT response instead: HttpKernelBrowser
     * ::filterResponse() already buffered the send into it
     * (`ob_start(…); $response->sendContent();`), so the streaming really
     * happened and this is what came out of it.
     */
    private function streamedContent(): string
    {
        return (string) $this->client->getInternalResponse()->getContent();
    }

    /**
     * The route requirement has to allow slashes: a key is a PATH, and the
     * default {key} placeholder would stop at the first one.
     */
    public function testTheRouteCarriesAMultiSegmentKey(): void
    {
        $key = $this->storeUnder('granted/obs/deeper/still');
        $this->signIn();

        self::assertStringContainsString('/', $key);
        $this->client->request('GET', '/storage/evidence/'.$key);

        self::assertResponseIsSuccessful();
    }

    public function testTheThumbnailIsServedByTheSameRoute(): void
    {
        $key = $this->storeUnder('granted/obs-2');
        $this->signIn();

        $this->client->request('GET', '/storage/evidence/'.$key.'.thumb.jpg');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');
    }

    public function testItAnswersWithTheStoredTypeAndLength(): void
    {
        $key = $this->storeUnder('granted/obs-3');
        $this->signIn();

        $this->client->request('GET', '/storage/evidence/'.$key);

        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');
        self::assertResponseHeaderSame('Content-Length', (string) filesize(self::IMAGES.'/landscape-800x600.jpg'));
    }

    /**
     * Evidence must never sit in a shared cache. "private" keeps proxies out;
     * "nosniff" stops a browser from re-deciding the type we just declared.
     */
    public function testEvidenceIsNeverCachedByAnythingSharedAndIsNeverSniffed(): void
    {
        $key = $this->storeUnder('granted/obs-4');
        $this->signIn();

        $this->client->request('GET', '/storage/evidence/'.$key);

        $cacheControl = $this->client->getResponse()->headers->get('Cache-Control') ?? '';
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringNotContainsString('public', $cacheControl);
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
    }

    /** Nothing is ever offered as a download that a browser would then execute. */
    public function testItIsServedInlineUnderASafeGeneratedFilename(): void
    {
        $key = $this->storeUnder('granted/obs-5');
        $this->signIn();

        $this->client->request('GET', '/storage/evidence/'.$key);

        $disposition = $this->client->getResponse()->headers->get('Content-Disposition') ?? '';
        self::assertStringContainsString('inline', $disposition);
        self::assertStringNotContainsString('..', $disposition);
    }

    public function testAVisitorWhoIsNotSignedInIsRefused(): void
    {
        $key = $this->storeUnder('granted/obs-6');

        $this->client->request('GET', '/storage/evidence/'.$key);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testASignedInUserTheOwningModuleRefusesGetsNothing(): void
    {
        $key = $this->storeUnder('denied/obs-7');
        $this->signIn();

        $this->client->request('GET', '/storage/evidence/'.$key);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    /**
     * DENY BY DEFAULT. Nothing claims the "orphan/" prefix — no module owns it
     * — so it is refused even though the bytes are really there and the user is
     * really signed in.
     */
    public function testAKeyNoInstalledModuleClaimsIsRefusedEvenThoughTheFileExists(): void
    {
        $key = $this->storeUnder('orphan/obs-8');
        $this->signIn();

        /** @var EvidenceStorage $storage */
        $storage = self::getContainer()->get('test_public.'.EvidenceStorage::class);
        self::assertTrue($storage->exists($key), 'The test is only meaningful if the file is genuinely there.');

        $this->client->request('GET', '/storage/evidence/'.$key);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Permission is decided BEFORE existence, so a 404 can never be used to
     * enumerate which observations have photographs.
     */
    public function testAnUnauthorisedRequestForAMissingKeyIsStillA403NotA404(): void
    {
        $this->signIn();

        $this->client->request('GET', '/storage/evidence/orphan/obs-9/absent.jpg');

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testAnAuthorisedRequestForAMissingKeyIsA404(): void
    {
        $this->signIn();

        $this->client->request('GET', '/storage/evidence/granted/obs-10/absent.jpg');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    /** A traversing key is refused as a bad key, never resolved and never read. */
    public function testATraversingKeyIsRefused(): void
    {
        $this->signIn();

        $this->client->request('GET', '/storage/evidence/granted/../../../etc/passwd');

        self::assertContains(
            $this->client->getResponse()->getStatusCode(),
            [Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND],
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }
}
