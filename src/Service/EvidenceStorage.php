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

namespace Uhifadhi\Storage\Service;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\File\File;
use Uhifadhi\Storage\Exception\EvidenceNotFoundException;
use Uhifadhi\Storage\Exception\EvidenceRejectedException;
use Uhifadhi\Storage\Exception\EvidenceStorageFailedException;
use Uhifadhi\Storage\Exception\InvalidEvidenceKeyException;
use Uhifadhi\Storage\Model\EvidenceConstraints;
use Uhifadhi\Storage\Model\StoredFile;
use Uhifadhi\Storage\Thumbnail\ThumbnailGenerator;

/**
 * The evidence API every module consumes.
 *
 * Backed by the "storage.evidence" Flysystem storage, which is PRIVATE and
 * lives outside the document root. Field photographs are evidence — a snare, a
 * carcass, sometimes a person — and must not be retrievable by guessing a URL,
 * which is why nothing here ever returns a path and why the only way back out
 * is the authenticated route.
 *
 * Keys are RELATIVE, in and out. That is what lets a deployment move from a
 * local directory to Hetzner object storage without rewriting a single stored
 * row.
 */
final class EvidenceStorage
{
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly EvidenceConstraints $constraints,
        private readonly ThumbnailGenerator $thumbnails,
    ) {
    }

    /**
     * Validate, store, and try for one preview.
     *
     * @param \SplFileInfo $file      an UploadedFile from a request, or a plain File
     *                                for an importer that never touched HTTP
     * @param string       $keyPrefix the OWNING module's namespace for this record — the
     *                                same prefix its voter will claim
     * @param string       $clientKey one plain segment, unique within the prefix
     *
     * @throws EvidenceRejectedException      the file is not acceptable; retrying it is pointless
     * @throws EvidenceStorageFailedException the store failed; retrying is worthwhile
     * @throws InvalidEvidenceKeyException    the caller built a key that is not relative
     */
    public function store(\SplFileInfo $file, string $keyPrefix, string $clientKey): StoredFile
    {
        // Validate FIRST, before a key is built or a directory is created: a
        // rejected upload must leave no trace at all.
        $this->constraints->validate($file);

        $mimeType = $this->constraints->detect($file);
        $key = EvidenceKey::build($keyPrefix, $clientKey, EvidenceConstraints::extensionFor($mimeType));

        $sourcePath = $file->getPathname();
        $handle = @fopen($sourcePath, 'r');
        if (false === $handle) {
            throw EvidenceStorageFailedException::unreadableSource($sourcePath);
        }

        try {
            // writeStream, not write: a 12MB photograph should not be held in
            // memory in one piece, and on S3 this becomes a streaming PUT.
            $this->filesystem->writeStream($key, $handle);
        } catch (FilesystemException $exception) {
            throw EvidenceStorageFailedException::whileWriting($key, $exception);
        } finally {
            // Flysystem closes the handle on some paths and not others; closing
            // an already-closed resource is a warning, so ask first.
            if (\is_resource($handle)) {
                fclose($handle);
            }
        }

        $size = $file->getSize();

        return new StoredFile(
            $key,
            $mimeType ?? 'application/octet-stream',
            false === $size ? 0 : $size,
            $this->writeThumbnail($key, $sourcePath, $mimeType),
        );
    }

    /**
     * A resource, ready to hand to a StreamedResponse.
     *
     * @return resource
     *
     * @throws EvidenceNotFoundException
     * @throws InvalidEvidenceKeyException
     */
    public function stream(string $key)
    {
        EvidenceKey::assertValid($key);

        try {
            return $this->filesystem->readStream($key);
        } catch (UnableToReadFile $exception) {
            // Distinguished from a genuine storage failure so the serving route
            // can answer 404 here and 500 there — two very different signals.
            if (!$this->exists($key)) {
                throw EvidenceNotFoundException::forKey($key);
            }

            throw EvidenceStorageFailedException::whileReading($key, $exception);
        } catch (FilesystemException $exception) {
            throw EvidenceStorageFailedException::whileReading($key, $exception);
        }
    }

    /**
     * Remove a stored file AND its preview.
     *
     * Both, always. Deleting evidence and leaving a readable thumbnail of it
     * behind is the worst kind of half-done.
     *
     * Deleting something already gone is not an error — Flysystem's delete() is
     * specified as idempotent, and a retried cleanup should not throw.
     *
     * @throws InvalidEvidenceKeyException
     */
    public function delete(string $key): void
    {
        EvidenceKey::assertValid($key);

        try {
            $this->filesystem->delete($key);
            $this->filesystem->delete(EvidenceKey::thumb($key));
        } catch (FilesystemException $exception) {
            throw EvidenceStorageFailedException::whileWriting($key, $exception);
        }
    }

    /** An invalid key names nothing, so it exists as much as anything else that is not there. */
    public function exists(string $key): bool
    {
        if (!EvidenceKey::isValid($key)) {
            return false;
        }

        try {
            return $this->filesystem->fileExists($key);
        } catch (FilesystemException) {
            return false;
        }
    }

    /**
     * The stored type, for the serving route's Content-Type.
     *
     * @throws InvalidEvidenceKeyException
     */
    public function mimeType(string $key): string
    {
        EvidenceKey::assertValid($key);

        try {
            return $this->filesystem->mimeType($key);
        } catch (FilesystemException) {
            // Never guessed from the key: an unknown type is served as an
            // opaque download, which is the safe reading of "we are not sure".
            return 'application/octet-stream';
        }
    }

    /**
     * @throws InvalidEvidenceKeyException
     */
    public function byteSize(string $key): int
    {
        EvidenceKey::assertValid($key);

        try {
            return $this->filesystem->fileSize($key);
        } catch (FilesystemException $exception) {
            throw EvidenceStorageFailedException::whileReading($key, $exception);
        }
    }

    /**
     * One ~400px JPEG beside the original, or an honest null.
     *
     * Nothing in here may throw. If Imagick is absent and GD cannot read the
     * HEIC an iPhone just sent, the photograph is still stored and thumbKey is
     * null — the alternative, failing the upload, would lose field evidence
     * over a missing image library.
     */
    private function writeThumbnail(string $key, string $sourcePath, ?string $mimeType): ?string
    {
        if (null === $mimeType) {
            return null;
        }

        $bytes = $this->thumbnails->generate($sourcePath, $mimeType);
        if (null === $bytes) {
            return null;
        }

        $thumbKey = EvidenceKey::thumb($key);

        try {
            $this->filesystem->write($thumbKey, $bytes);
        } catch (FilesystemException) {
            // The original is already safely stored. Report no preview rather
            // than a key that points at nothing.
            return null;
        }

        return $thumbKey;
    }
}
