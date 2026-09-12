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

namespace Uhifadhi\Storage\Exception;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Storage\Model\Bytes;
use Uhifadhi\Storage\Service\ServerUploadLimitService;

/**
 * THE REFUSAL SENTENCES, WRITTEN ONCE.
 *
 * The design is exact about this: "Every refusal comes back as ONE SENTENCE
 * WRITTEN BY WHOEVER REFUSED and is drawn on the row or in the tile that caused
 * it. The component never invents an error message." So the words live here, in
 * the bundle that does the refusing, and the component prints what it is given
 * and has no vocabulary of its own.
 *
 * EVERY UPLOAD REFUSAL ENDS THE SAME WAY — "Nothing was written." The design
 * asks for the refusal PLUS what did not happen, because the thing a person
 * actually needs to know after a failure is whether they now have half a record.
 * A removal refusal does not carry it: nothing was going to be written either
 * way, and the file is still there.
 *
 * THE STATUS CODE TRAVELS WITH THE SENTENCE rather than being decided by the
 * controller, so a new refusal cannot be added in one place and mapped in
 * another.
 */
final class UploadRefusedException extends \RuntimeException
{
    private const string NOTHING = ' Nothing was written.';

    public function __construct(
        string $message,
        public readonly int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /**
     * A kind this target does not take.
     *
     * $accepts is what the TARGET does take, in words, and it comes from the
     * target's own {@see \Uhifadhi\Storage\Model\UploadConstraints} — the very
     * list the zone printed its kinds line from, so the promise and the refusal
     * cannot disagree. Naming what the FILE is instead would be the easier
     * sentence and the less useful one: somebody holding a file that did not
     * work needs to know which one would have.
     */
    public static function kindNotAllowed(string $accepts): self
    {
        return new self(\sprintf('That file is not %s.%s', $accepts, self::NOTHING));
    }

    public static function tooLarge(int $maxBytes): self
    {
        return new self(\sprintf('Larger than the %s limit this storage accepts.%s', Bytes::human($maxBytes), self::NOTHING));
    }

    public static function incomplete(): self
    {
        return new self('That upload did not arrive intact.'.self::NOTHING);
    }

    /**
     * THE SERVER'S OWN CEILING, NAMED. PHP truncates anything over
     * `upload_max_filesize` / `post_max_size` and reports it through the same
     * failed isValid() as a broken transfer, so reading the codes alike answered
     * a server-side cap with a sentence about a phone and a network. The number
     * is the one that actually refused — never the configured
     * `storage.evidence.max_bytes`, which a person in this situation cannot act
     * on, because it is the LARGER of the two and nothing enforced it.
     */
    public static function exceedsServerLimit(int $serverMaxBytes): self
    {
        return new self(\sprintf('That file is larger than this server accepts (%s).%s', Bytes::human($serverMaxBytes), self::NOTHING));
    }

    /**
     * THE SENTENCE FOR A FAILED UPLOAD, CHOSEN THE WAY SYMFONY CHOOSES ITS OWN.
     *
     * `UploadedFile::getErrorMessage()` splits the codes exactly here: the two
     * size codes name a limit, everything else describes a transfer that did not
     * finish. `UPLOAD_ERR_FORM_SIZE` joins the ini cap because to whoever is
     * holding the file the two are one fact, and because the form field that
     * raises it is generated from the same ceiling.
     *
     * @see UploadedFile::getErrorMessage()
     *
     * $serverMaxBytes is passed in so the decision is testable without an ini
     * that cannot be changed at runtime; null means "whatever PHP is running
     * with", which is the only answer a request can give.
     */
    public static function forFailedUpload(UploadedFile $file, ?int $serverMaxBytes = null): self
    {
        return match ($file->getError()) {
            \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => self::exceedsServerLimit($serverMaxBytes ?? ServerUploadLimitService::phpAccepts()),
            \UPLOAD_ERR_NO_FILE => self::noFile(),
            default => self::incomplete(),
        };
    }

    public static function noFile(): self
    {
        return new self('No file arrived with that upload.'.self::NOTHING);
    }

    /**
     * Nobody claims the kind. A 404 rather than a 422: the target names
     * something that is not there, which is the same fact as a missing record
     * and must read the same way to anyone probing.
     */
    public static function unknownTarget(): self
    {
        return new self('Nothing on this platform answers for that target.'.self::NOTHING, Response::HTTP_NOT_FOUND);
    }

    public static function noSuchRecord(): self
    {
        return new self('No record answers to that target.'.self::NOTHING, Response::HTTP_NOT_FOUND);
    }

    public static function notPermitted(): self
    {
        return new self('You may not attach a file to this record.'.self::NOTHING, Response::HTTP_FORBIDDEN);
    }

    /**
     * The store itself failed, or the module refused after the bytes had landed.
     * Distinguished from every refusal above because retrying it is WORTHWHILE —
     * the component keeps its Retry on the row for exactly this case.
     */
    public static function storageFailed(?\Throwable $previous = null): self
    {
        return new self('The storage could not keep that file.'.self::NOTHING, Response::HTTP_INTERNAL_SERVER_ERROR, $previous);
    }

    public static function noSuchFile(): self
    {
        return new self('Nothing on this platform holds that file.', Response::HTTP_NOT_FOUND);
    }

    public static function mayNotRemove(): self
    {
        return new self('You may not take that file off this record.', Response::HTTP_FORBIDDEN);
    }

    /**
     * The record refused at the moment of removal — the guard is a statement
     * about a moment, and the moment can pass between drawing the page and
     * pressing the button.
     */
    public static function removalRefused(\Throwable $previous): self
    {
        return new self('That file could not be taken off its record.', Response::HTTP_CONFLICT, $previous);
    }
}
