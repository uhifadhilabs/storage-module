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
use Symfony\Component\Mime\MimeTypes;
use Uhifadhi\Storage\Enum\FileKindEnum;
use Uhifadhi\Storage\Exception\EvidenceRejectedException;
use Uhifadhi\Storage\Service\ServerUploadLimitService;

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
     * WHAT AN UNCONFIGURED DEPLOYMENT ACCEPTS — one of each of the hub's three
     * kinds, because the hub's own filter row reads "Photos / Documents /
     * Tracks" and a default that covered only the first made two of those chips
     * permanently empty. Worse, it silently OVERRULED any module: a target whose
     * records genuinely take a GPX was refused by a list nobody had chosen, with
     * a sentence about photographs.
     *
     * A deployment may NARROW this (the config key is `allowed_mime_types`);
     * widening it is allowed too, and a widened type is keyed by its own
     * extension and simply gets no thumbnail unless an engine can read it.
     *
     * THE LAST TWO ARE CARRIERS, NOT KINDS. fileinfo reads BYTES and has never
     * heard of GPX, so a real track file detects as `text/xml`. They are here so
     * a track can get through, and {@see FileKindEnum::isCarrier()} is what stops
     * them being advertised as a kind of their own.
     *
     * @var list<string>
     */
    public const array DEFAULT_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/heic', 'image/heif', 'image/webp',
        'application/pdf',
        'application/gpx+xml', 'application/xml', 'text/xml',
    ];

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
        //
        // WHICH FAILURE IT WAS, THOUGH, IS TWO DIFFERENT FACTS. PHP reports its
        // own `upload_max_filesize` / `post_max_size` refusal through the same
        // failed isValid() as a truncated transfer, and reading them alike blames
        // the network for an ini line. The split is the one
        // UploadedFile::getErrorMessage() draws.
        if ($file instanceof UploadedFile && !$file->isValid()) {
            throw match ($file->getError()) {
                \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => EvidenceRejectedException::exceedsServerLimit(ServerUploadLimitService::phpAccepts()),
                default => EvidenceRejectedException::uploadIncomplete($file->getErrorMessage()),
            };
        }

        $size = $file->getSize();
        if (false !== $size && $size > $this->maxBytes) {
            throw EvidenceRejectedException::tooLarge($size, $this->maxBytes);
        }

        $mimeType = $this->detect($file);
        if (!$this->allows($mimeType)) {
            // The sentence names what IS accepted, read from this very list — so
            // a deployment that narrowed to tracks says "not a GPX track" rather
            // than the photograph wording the first allowlist was written for.
            throw EvidenceRejectedException::unsupportedType($mimeType, $this->describe());
        }
    }

    /**
     * WHAT THIS DEPLOYMENT ACCEPTS, IN WORDS — "a photograph, document or GPX
     * track", with the article, ready to drop into a sentence.
     *
     * Derived from the allowlist itself rather than written beside it, which is
     * the only way a refusal cannot name something the guard does not enforce.
     */
    public function describe(): string
    {
        return FileKindEnum::phrase(FileKindEnum::inMimeTypes($this->allowedMimeTypes));
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
     * The type is looked up in symfony/mime's table, whose getExtensions()
     * returns "the extensions for the given MIME type in decreasing order of
     * preference" (MimeTypesInterface) — so the first entry is the answer, and
     * the table already gives 'jpg' for image/jpeg, 'heic' for image/heic and
     * 'pdf' for application/pdf.
     * https://symfony.com/doc/current/components/mime.html#guessing-the-mime-type
     *
     * No hand-written map sits in front of it: every type in the default
     * allowlist gets the extension this platform wants from the table itself,
     * and EvidenceConstraintsTest pins each one, so a table change fails the
     * build rather than quietly renaming tomorrow's evidence.
     *
     * A type this deployment allows but that nothing can name is REFUSED —
     * guessing one is how a widened allowlist would key a signed PDF ".jpg".
     *
     * @throws EvidenceRejectedException
     */
    public static function extensionFor(?string $mimeType): string
    {
        // An undetectable type is accepted by the guard above and recorded by
        // EvidenceStorage as application/octet-stream; the key says the same
        // rather than claiming to be a photograph.
        $mimeType ??= 'application/octet-stream';

        $extensions = MimeTypes::getDefault()->getExtensions($mimeType);
        if ([] === $extensions) {
            throw EvidenceRejectedException::unnameableType($mimeType);
        }

        return $extensions[0];
    }
}
