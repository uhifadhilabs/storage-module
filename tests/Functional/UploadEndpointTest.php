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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Storage\Controller\UploadController;
use Uhifadhi\Storage\Exception\UploadRefusedException;
use Uhifadhi\Storage\Service\EvidenceStorage;
use Uhifadhi\Storage\Service\UploadService;
use Uhifadhi\Storage\Tests\Integration\Fixtures\StubUploadTarget;

/**
 * THE ONE ENDPOINT, over real HTTP.
 *
 * The design's contract is two calls and one rule about refusals: every refusal
 * comes back as ONE SENTENCE WRITTEN BY WHOEVER REFUSED, and the component never
 * invents an error message. So each refusal below is asserted on its words, not
 * only on its status code — a 422 with an empty body would pass a status
 * assertion and draw a blank row.
 *
 * WHAT IS DELIBERATELY NOT HERE: anything about how the component draws. That is
 * {@see UploadComponentTest}, and the string contract between the two is
 * {@see \Uhifadhi\Storage\Tests\Unit\Template\UploadSeamTest} — a green HTTP
 * suite has proved nothing about a page before, and the seam test is the lesson.
 */
#[CoversClass(UploadController::class)]
#[CoversClass(UploadService::class)]
#[CoversClass(UploadRefusedException::class)]
final class UploadEndpointTest extends FilesTestCase
{
    private ?string $token = null;

    private const string PHOTO = __DIR__.'/../Fixtures/images/tiny-100x80.jpg';
    private const string NOT_AN_IMAGE = __DIR__.'/../Fixtures/images/not-an-image.php';
    private const string TRACK = __DIR__.'/../Fixtures/tracks/walk.gpx';

    public function testAFileIsStoredAndTheModuleSaysWhatItBecame(): void
    {
        $client = $this->ranger(self::createClient());

        $this->upload($client, 'stub:open', self::PHOTO, 'IMG_1204.jpg');

        self::assertResponseIsSuccessful();
        $key = $this->field($client, 'key');

        self::assertSame('IMG_1204.jpg', $this->field($client, 'label'));
        self::assertSame('stored', $this->field($client, 'kind'));
        self::assertStringStartsWith('stub/open/', $key);
        self::assertSame('/stub/'.$key, $this->field($client, 'href'));
        self::assertGreaterThan(0, $this->json($client)['bytes']);
        // A JPEG this suite can decode, so a preview was made and its URL comes
        // back — a page that draws thumbnails is handed one in the receipt.
        self::assertStringContainsString('/storage/evidence/', $this->field($client, 'thumbnail'));

        self::assertTrue($this->storage()->exists($key), 'the bytes are in the private storage');
    }

    public function testTheOwningModuleIsHandedTheStoredFile(): void
    {
        $client = $this->ranger(self::createClient());

        $this->upload($client, 'stub:open', self::PHOTO, 'IMG_1204.jpg');

        self::assertSame([$this->field($client, 'key')], $this->target()->received);
    }

    /**
     * "parsed, nothing kept" — the design's boundary-import outcome. The chip
     * says what the module made of the file and the blob does not outlive it.
     */
    public function testATargetThatKeepsNothingLeavesNoBytesBehind(): void
    {
        $client = $this->ranger(self::createClient());

        $this->upload($client, 'stub:parsed', self::PHOTO, 'boundary.jpg');

        self::assertResponseIsSuccessful();

        self::assertSame('parsed', $this->field($client, 'kind'));
        self::assertNull($this->json($client)['thumbnail']);
        self::assertFalse($this->storage()->exists($this->field($client, 'key')), 'the blob was thrown away with the receipt');
    }

    /**
     * A TRACK REACHES A TRACK TARGET. It used to be refused with "that file is
     * not a photograph": the shipped DEFAULT allowlist was images only, so it
     * overruled a target whose records genuinely take a GPX, and answered for it
     * in words written for a camera.
     */
    public function testAGpxIsStoredOnATargetThatTakesTracks(): void
    {
        $client = $this->ranger(self::createClient());

        $this->upload($client, 'stub:track', self::TRACK, 'patrol_0822.gpx');

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('stub/track/', $this->field($client, 'key'));
    }

    /** And on any target that has not narrowed, because the default now covers it. */
    public function testAGpxIsStoredOnATargetThatSimplyTakesWhatTheDeploymentDoes(): void
    {
        $client = $this->ranger(self::createClient());

        $this->upload($client, 'stub:open', self::TRACK, 'patrol_0822.gpx');

        self::assertResponseIsSuccessful();
    }

    /**
     * THE REFUSAL NAMES WHAT THE TARGET TAKES, never what the file is. Somebody
     * holding a file that did not work needs to know which one would have — and
     * the noun is read from the same list the zone printed its kinds line from.
     */
    public function testAKindThisTargetDoesNotTakeIsRefusedInTheTargetsOwnWords(): void
    {
        $client = $this->ranger(self::createClient());

        $this->upload($client, 'stub:track', self::PHOTO, 'IMG_1204.jpg');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(
            'That file is not a GPX track. Nothing was written.',
            $this->field($client, 'error'),
        );
    }

    /**
     * THE RIGHT KIND, THE WRONG SPELLING. A target that takes PNGs and not
     * JPEGs cannot answer "that is not a photograph" — it is one. The sentence
     * names the spellings instead, and they are the spellings the line printed.
     */
    public function testARefusalInsideOneKindNamesTheSpellings(): void
    {
        $client = $this->ranger(self::createClient());

        $this->upload($client, 'stub:png', self::PHOTO, 'IMG_1204.jpg');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(
            'That file is not one of png. Nothing was written.',
            $this->field($client, 'error'),
        );
    }

    /**
     * THE DEPLOYMENT'S OWN REFUSAL, ANSWERED IN THE TARGET'S WORDS. The person
     * dropped this on a target, so the sentence is about that target whichever
     * of the two allowlists actually turned it away.
     */
    public function testAFileThatIsNotEvenAKindTheDeploymentKnowsIsRefused(): void
    {
        $client = $this->ranger(self::createClient());

        $this->upload($client, 'stub:open', self::NOT_AN_IMAGE, 'sneaky.jpg');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(
            'That file is not a photograph, document or GPX track. Nothing was written.',
            $this->field($client, 'error'),
        );
    }

    public function testAFileOverTheLimitIsRefusedInTheDesignsOwnWords(): void
    {
        $client = $this->ranger(self::createClient());

        $this->upload($client, 'stub:tiny', self::PHOTO, 'IMG_1204.jpg');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(
            'Larger than the 64 B limit this storage accepts. Nothing was written.',
            $this->field($client, 'error'),
        );
    }

    public function testARecordThatWillNotTakeAFileFromThisPersonRefuses(): void
    {
        $client = $this->ranger(self::createClient());

        $this->upload($client, 'stub:locked', self::PHOTO, 'IMG_1204.jpg');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(
            'You may not attach a file to this record. Nothing was written.',
            $this->field($client, 'error'),
        );
        self::assertSame([], $this->target()->received);
    }

    public function testATargetNoModuleAnswersForIsRefused(): void
    {
        $client = $this->ranger(self::createClient());

        $this->upload($client, 'nobody:0199abcd', self::PHOTO, 'IMG_1204.jpg');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame(
            'Nothing on this platform answers for that target. Nothing was written.',
            $this->field($client, 'error'),
        );
    }

    public function testARecordThatIsNotThereIsRefusedTheSameWay(): void
    {
        $client = $this->ranger(self::createClient());

        $this->upload($client, 'stub:0199abcd', self::PHOTO, 'IMG_1204.jpg');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertStringContainsString('Nothing was written.', $this->field($client, 'error'));
    }

    public function testAnUploadWithNoFileIsRefusedRatherThanCrashing(): void
    {
        $client = $this->ranger(self::createClient());

        $client->request('POST', '/files/upload', ['target' => 'stub:open', '_token' => $this->token($client)]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('Nothing was written.', $this->field($client, 'error'));
    }

    public function testAnUploadWithoutATokenIsRefused(): void
    {
        $client = $this->ranger(self::createClient());

        $client->request('POST', '/files/upload', ['target' => 'stub:open'], ['file' => self::file(self::PHOTO, 'a.jpg')]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame([], $this->target()->received);
    }

    /**
     * A VISITOR WHO IS NOT SIGNED IN IS OFFERED NO UPLOAD AND CANNOT MAKE ONE.
     *
     * Both halves, because the first is what makes the second unreachable: the
     * component draws nothing for them, so no page ever mints them a token, so
     * there is no way for such a request to get past the token check to the
     * user check behind it.
     */
    public function testNobodySignedInMayUploadAnything(): void
    {
        $client = self::createClient();

        $page = $client->request('GET', '/stub/upload/stub:open');
        self::assertCount(0, $page->filter('[data-upl-token]'), 'no component, so no token to send');

        $client->request(
            'POST',
            '/files/upload',
            ['target' => 'stub:open'],
            ['file' => self::file(self::PHOTO, 'IMG_1204.jpg')],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame([], $this->target()->received);
    }

    public function testAFileIsTakenBackOffItsRecordAndItsBytesGo(): void
    {
        $client = $this->ranger(self::createClient());
        $this->upload($client, 'stub:open', self::PHOTO, 'IMG_1204.jpg');
        $key = $this->field($client, 'key');

        $client->request('DELETE', '/files/'.$key, [], [], ['HTTP_X-CSRF-Token' => $this->token($client)]);

        self::assertResponseIsSuccessful();
        self::assertSame($key, $this->field($client, 'key'));
        // The record is unpicked BEFORE the bytes go, so a module that refuses
        // after all leaves the file where it was.
        self::assertSame([$key], $this->target()->unpicked);
        self::assertFalse($this->storage()->exists($key));
    }

    public function testARecordThatWillNotLetGoKeepsItsFile(): void
    {
        $client = $this->ranger(self::createClient());
        $this->upload($client, 'stub:sealed', self::PHOTO, 'IMG_1204.jpg');
        $key = $this->field($client, 'key');

        $client->request('DELETE', '/files/'.$key, [], [], ['HTTP_X-CSRF-Token' => $this->token($client)]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('You may not take that file off this record.', $this->field($client, 'error'));
        self::assertSame([], $this->target()->unpicked);
        self::assertTrue($this->storage()->exists($key), 'a refused removal leaves the bytes alone');
    }

    public function testAKeyNoModuleWroteCannotBeRemovedThroughAnother(): void
    {
        $client = $this->ranger(self::createClient());

        $client->request('DELETE', '/files/nobody/0199abcd/ef12.jpg', [], [], ['HTTP_X-CSRF-Token' => $this->token($client)]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testARemovalWithoutATokenIsRefused(): void
    {
        $client = $this->ranger(self::createClient());
        $this->upload($client, 'stub:open', self::PHOTO, 'IMG_1204.jpg');
        $key = $this->field($client, 'key');

        $client->request('DELETE', '/files/'.$key);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertTrue($this->storage()->exists($key));
    }

    private function upload(KernelBrowser $client, string $target, string $path, string $name): void
    {
        $client->request(
            'POST',
            '/files/upload',
            ['target' => $target],
            ['file' => self::file($path, $name)],
            ['HTTP_X-CSRF-Token' => $this->token($client)],
        );
    }

    private static function file(string $path, string $name): UploadedFile
    {
        return new UploadedFile($path, $name, test: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(KernelBrowser $client): array
    {
        $decoded = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /** One field of the reply, asserted to be a string before it is used as one. */
    private function field(KernelBrowser $client, string $name): string
    {
        $value = $this->json($client)[$name] ?? null;
        self::assertIsString($value, $name.' must come back as a sentence or an identifier');

        return $value;
    }

    /**
     * THE TOKEN THE COMPONENT MINTED, read off a page that rendered it.
     *
     * Not fetched from the token manager: a token this suite minted for itself
     * would prove only that the endpoint accepts its own arithmetic. Reading it
     * out of the rendered component is what proves the two halves agree — the
     * same reason the file page's removal test reads its token out of the form.
     */
    private function token(KernelBrowser $client): string
    {
        if (null === $this->token) {
            $crawler = $client->request('GET', '/stub/upload/stub:open');
            $this->token = (string) $crawler->filter('[data-upl-token]')->first()->attr('data-upl-token');
        }

        return $this->token;
    }

    private function storage(): EvidenceStorage
    {
        /** @var EvidenceStorage $storage */
        $storage = self::getContainer()->get('test_public.'.EvidenceStorage::class);

        return $storage;
    }

    private function target(): StubUploadTarget
    {
        /** @var StubUploadTarget $target */
        $target = self::getContainer()->get('test_public.'.StubUploadTarget::class);

        return $target;
    }
}
