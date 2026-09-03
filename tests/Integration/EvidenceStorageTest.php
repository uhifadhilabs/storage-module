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

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Uhifadhi\Storage\Exception\EvidenceRejectedException;
use Uhifadhi\Storage\Service\EvidenceStorage;

/**
 * The API modules consume, against a REAL local adapter writing REAL bytes
 * into a temp directory. Anything mocked here would be a test of the mock.
 */
final class EvidenceStorageTest extends KernelTestCase
{
    private const string IMAGES = __DIR__.'/../Fixtures/images';

    private EvidenceStorage $storage;

    protected function setUp(): void
    {
        self::bootKernel();

        // A clean store per test: these assertions are about what landed.
        $directory = TestKernel::evidenceDirectory();
        if (is_dir($directory)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $item) {
                /** @var \SplFileInfo $item */
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
        }

        /** @var EvidenceStorage $storage */
        $storage = self::getContainer()->get('test_public.'.EvidenceStorage::class);
        $this->storage = $storage;
    }

    private function upload(string $fixture, string $clientName = 'photo.jpg', string $clientType = 'image/jpeg'): UploadedFile
    {
        // A copy, because an UploadedFile in test mode still points at the real
        // path and store() must never be able to consume the fixture itself.
        $copy = sys_get_temp_dir().'/storage-module-tests/'.bin2hex(random_bytes(6)).'-'.basename($fixture);
        @mkdir(\dirname($copy), 0o775, true);
        copy(self::IMAGES.'/'.$fixture, $copy);

        return new UploadedFile($copy, $clientName, $clientType, test: true);
    }

    public function testItStoresAPhotographAndReportsItRelatively(): void
    {
        $stored = $this->storage->store($this->upload('landscape-800x600.jpg'), 'granted/obs-1', 'aaa-bbb');

        // RELATIVE keys only. A module persists this string, and an absolute
        // path in that column would break the moment the store moves to S3.
        self::assertSame('granted/obs-1/aaa-bbb.jpg', $stored->key);
        self::assertStringNotContainsString(TestKernel::evidenceDirectory(), $stored->key);
        self::assertStringStartsNotWith('/', $stored->key);

        self::assertSame('image/jpeg', $stored->mimeType);
        self::assertSame(filesize(self::IMAGES.'/landscape-800x600.jpg'), $stored->byteSize);

        self::assertTrue($this->storage->exists($stored->key));
        self::assertFileExists(TestKernel::evidenceDirectory().'/granted/obs-1/aaa-bbb.jpg');
    }

    public function testTheStoredBytesAreTheOriginalBytes(): void
    {
        $stored = $this->storage->store($this->upload('landscape-800x600.jpg'), 'granted/obs-1', 'aaa');

        $resource = $this->storage->stream($stored->key);
        $read = stream_get_contents($resource);
        fclose($resource);

        self::assertSame(file_get_contents(self::IMAGES.'/landscape-800x600.jpg'), $read);
    }

    /** stream() hands back a resource, which is what StreamedResponse wants. */
    public function testStreamReturnsAResourceRatherThanAStringInMemory(): void
    {
        $stored = $this->storage->store($this->upload('landscape-800x600.jpg'), 'granted/obs-1', 'aaa');

        $resource = $this->storage->stream($stored->key);

        // A stream, specifically — that is what StreamedResponse can consume
        // without the bytes ever being assembled in memory.
        self::assertSame('stream', get_resource_type($resource));
        fclose($resource);
    }

    public function testAThumbnailIsWrittenBesideTheOriginal(): void
    {
        $stored = $this->storage->store($this->upload('landscape-800x600.jpg'), 'granted/obs-1', 'aaa');

        self::assertSame('granted/obs-1/aaa.jpg.thumb.jpg', $stored->thumbKey);
        self::assertTrue($this->storage->exists($stored->thumbKey));

        $resource = $this->storage->stream($stored->thumbKey);
        $bytes = stream_get_contents($resource);
        fclose($resource);

        self::assertIsString($bytes);
        $size = getimagesizefromstring($bytes);
        self::assertIsArray($size);
        self::assertSame([400, 300], [$size[0], $size[1]]);
    }

    /**
     * THE HONEST NULL, end to end. A HEIC arrives, nothing on this machine can
     * decode it, and the upload still succeeds — with thumbKey saying so
     * plainly rather than pointing at a file that is not there.
     */
    public function testAHeicUploadSucceedsWithoutAThumbnailWhenNothingCanDecodeIt(): void
    {
        $stored = $this->storage->store(
            $this->upload('photo.heic', 'IMG_0042.HEIC', 'image/heic'),
            'granted/obs-2',
            'ccc',
        );

        self::assertSame('granted/obs-2/ccc.heic', $stored->key);
        self::assertSame('image/heic', $stored->mimeType);
        self::assertTrue($this->storage->exists($stored->key), 'The original must be kept whatever happens to the thumbnail.');

        if (null === $stored->thumbKey) {
            self::assertFalse($this->storage->exists('granted/obs-2/ccc.heic.thumb.jpg'));

            return;
        }

        // An ImageMagick built with libheif CAN decode it — then the variant
        // must really be there. Either answer is honest; a lie is not.
        self::assertTrue($this->storage->exists($stored->thumbKey));
    }

    /**
     * The extension comes from the DETECTED type, never the filename — the
     * rule patrol already applies, now applied for every module at once.
     */
    public function testAFilenameCannotChooseTheExtension(): void
    {
        $stored = $this->storage->store(
            $this->upload('portrait-300x900.png', 'evil.php', 'image/png'),
            'granted/obs-3',
            'ddd',
        );

        self::assertSame('granted/obs-3/ddd.png', $stored->key);
        self::assertStringEndsNotWith('.php', $stored->key);
    }

    public function testAFileThatIsNotAPhotographIsRefusedAndNothingIsWritten(): void
    {
        $upload = $this->upload('not-an-image.php', 'holiday.jpg', 'image/jpeg');

        try {
            $this->storage->store($upload, 'granted/obs-4', 'eee');
            self::fail('A PHP script was stored as evidence.');
        } catch (EvidenceRejectedException) {
            // The store must be untouched: a rejected upload leaves no trace.
            self::assertFalse($this->storage->exists('granted/obs-4/eee.jpg'));
            self::assertFalse($this->storage->exists('granted/obs-4/eee.php'));
            self::assertDirectoryDoesNotExist(TestKernel::evidenceDirectory().'/granted/obs-4');
        }
    }

    public function testDeleteRemovesTheOriginalAndItsThumbnailTogether(): void
    {
        $stored = $this->storage->store($this->upload('landscape-800x600.jpg'), 'granted/obs-5', 'fff');
        self::assertNotNull($stored->thumbKey);

        $this->storage->delete($stored->key);

        self::assertFalse($this->storage->exists($stored->key));
        // Deleting evidence and leaving a readable preview of it behind would
        // be the worst kind of half-done.
        self::assertFalse($this->storage->exists($stored->thumbKey));
    }

    public function testDeletingSomethingAlreadyGoneIsNotAnError(): void
    {
        $this->storage->delete('granted/obs-6/never-existed.jpg');

        self::assertFalse($this->storage->exists('granted/obs-6/never-existed.jpg'));
    }

    public function testExistsIsFalseForAKeyThatWasNeverStored(): void
    {
        self::assertFalse($this->storage->exists('granted/nothing/here.jpg'));
    }

    /**
     * Key discipline is enforced at the DOOR, not left to callers to remember.
     */
    public function testItRefusesToStoreUnderATraversingPrefix(): void
    {
        $this->expectException(\Uhifadhi\Storage\Exception\InvalidEvidenceKeyException::class);

        $this->storage->store($this->upload('landscape-800x600.jpg'), '../../../etc', 'passwd');
    }

    public function testItRefusesToStreamATraversingKey(): void
    {
        $this->expectException(\Uhifadhi\Storage\Exception\InvalidEvidenceKeyException::class);

        $this->storage->stream('../../../etc/passwd');
    }

    public function testItRefusesToDeleteATraversingKey(): void
    {
        $this->expectException(\Uhifadhi\Storage\Exception\InvalidEvidenceKeyException::class);

        $this->storage->delete('../../../etc/passwd');
    }

    public function testStreamingAKeyThatIsNotThereIsAMissingFileNotACrash(): void
    {
        $this->expectException(\Uhifadhi\Storage\Exception\EvidenceNotFoundException::class);

        $this->storage->stream('granted/obs-7/absent.jpg');
    }

    /** A plain File (not an upload) works too — importers do not go through HTTP. */
    public function testItAcceptsAPlainFileAsWellAsAnUpload(): void
    {
        $copy = sys_get_temp_dir().'/storage-module-tests/'.bin2hex(random_bytes(6)).'.jpg';
        @mkdir(\dirname($copy), 0o775, true);
        copy(self::IMAGES.'/tiny-100x80.jpg', $copy);

        $stored = $this->storage->store(new File($copy), 'granted/import', 'ggg');

        self::assertSame('granted/import/ggg.jpg', $stored->key);
        self::assertTrue($this->storage->exists($stored->key));
    }

    /** Two uploads under the same prefix do not collide on the client key. */
    public function testTwoPhotosUnderOneObservationCoexist(): void
    {
        $one = $this->storage->store($this->upload('landscape-800x600.jpg'), 'granted/obs-8', 'first');
        $two = $this->storage->store($this->upload('tiny-100x80.jpg'), 'granted/obs-8', 'second');

        self::assertNotSame($one->key, $two->key);
        self::assertTrue($this->storage->exists($one->key));
        self::assertTrue($this->storage->exists($two->key));
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
