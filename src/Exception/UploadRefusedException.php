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

use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Storage\Model\Bytes;

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
     * A kind this target does not take. Named by EXTENSION rather than by mime
     * type: "a jpg" is what somebody looking at their own files recognises,
     * where "image/jpeg" is what a developer does.
     */
    public static function kindNotAllowed(?string $extension): self
    {
        return new self(
            null === $extension || '' === $extension
                ? 'That kind of file is not one this target takes.'.self::NOTHING
                : \sprintf('A %s is not a kind this target takes.%s', $extension, self::NOTHING),
        );
    }

    public static function tooLarge(int $maxBytes): self
    {
        return new self(\sprintf('Larger than the %s limit this storage accepts.%s', Bytes::human($maxBytes), self::NOTHING));
    }

    public static function incomplete(): self
    {
        return new self('That upload did not arrive intact.'.self::NOTHING);
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
