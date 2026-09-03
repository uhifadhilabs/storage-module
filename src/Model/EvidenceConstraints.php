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

namespace Uhifadhi\Storage\Model;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Uhifadhi\Storage\Exception\EvidenceRejectedException;

/**
 * What this deployment accepts as evidence — and the guard that enforces it.
 *
 * These are the semantics patrol-module already applies in
 * PhotoSyncService::guardFile()/extensionFor(), lifted out so that every module
 * applies the SAME rule instead of each re-deriving it slightly differently.
 * The order of the three checks, the allowlist, and the "detected type, never
 * the filename" rule are all reproduced deliberately: patrol must be able to
 * adopt this class and reject exactly what it rejected before.
 *
 * Usable on its own, without the storage: a caller that wants to validate
 * before committing to an upload can construct one and call validate().
 */
final readonly class EvidenceConstraints
{
    /**
     * What a camera may send. Anything else is not a photograph.
     *
     * @var list<string>
     */
    public const array DEFAULT_MIME_TYPES = ['image/jpeg', 'image/png', 'image/heic', 'image/heif', 'image/webp'];

    public const int DEFAULT_MAX_BYTES = 12 * 1024 * 1024;

    /**
     * @param list<string> $allowedMimeTypes
     */
    public function __construct(
        public array $allowedMimeTypes,
        public int $maxBytes,
    ) {
        if ([] === $allowedMimeTypes) {
            throw new \InvalidArgumentException('An empty allowlist would accept nothing at all.');
        }
        if ($maxBytes < 1) {
            throw new \InvalidArgumentException('A size cap below one byte would accept nothing at all.');
        }
    }

    public static function default(): self
    {
        return new self(self::DEFAULT_MIME_TYPES, self::DEFAULT_MAX_BYTES);
    }

    /**
     * @throws EvidenceRejectedException
     */
    public function validate(\SplFileInfo $file): void
    {
        // Order matters and matches patrol's: an upload that did not arrive is
        // refused before anything is asked about bytes that may not be there.
        if ($file instanceof UploadedFile && !$file->isValid()) {
            throw EvidenceRejectedException::uploadIncomplete($file->getErrorMessage());
        }

        $size = $file->getSize();
        if (false !== $size && $size > $this->maxBytes) {
            throw EvidenceRejectedException::tooLarge($size, $this->maxBytes);
        }

        $mimeType = $this->detect($file);
        if (!$this->allows($mimeType)) {
            throw EvidenceRejectedException::unsupportedType($mimeType);
        }
    }

    /**
     * A null is ALLOWED, and that is patrol's behaviour rather than an
     * oversight: its guard reads `if (null !== $mimeType && !in_array(…))`, so
     * a file whose type cannot be determined at all passes. Changing that here
     * would change what patrol accepts the day it adopts this class. The
     * residual risk is carried elsewhere — such a file lands in a private
     * storage outside the document root and is only ever served back with a
     * fixed Content-Type and `nosniff`.
     */
    public function allows(?string $mimeType): bool
    {
        if (null === $mimeType) {
            return true;
        }

        return \in_array($mimeType, $this->allowedMimeTypes, true);
    }

    /**
     * The type read from the BYTES. Symfony's File::getMimeType() guesses with
     * fileinfo; a plain SplFileInfo has no such method, so it is wrapped.
     */
    public function detect(\SplFileInfo $file): ?string
    {
        return new \Symfony\Component\HttpFoundation\File\File($file->getPathname(), checkPath: false)->getMimeType();
    }

    /**
     * Derived from the DETECTED type, never from the client's filename: a
     * filename is attacker-controlled text, and letting it choose the extension
     * is how an upload directory ends up holding a ".php".
     *
     * The fall-through to "jpg" is patrol's, kept verbatim.
     */
    public static function extensionFor(?string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => 'png',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
