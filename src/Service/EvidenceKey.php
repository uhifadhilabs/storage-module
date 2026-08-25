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

namespace UhifadhiLabs\Storage\Service;

use UhifadhiLabs\Storage\Exception\InvalidEvidenceKeyException;

/**
 * Key discipline, in one place.
 *
 * A key is a RELATIVE path inside one storage. It is the only shape that
 * survives every use we put it to: modules persist it in their own tables, the
 * serving route carries it in a URL, and a voter reads its first segment to
 * decide who owns it. An absolute path breaks the first the moment the store
 * moves to S3; a traversing path breaks the other two immediately.
 *
 * The charset is deliberately narrow. Flysystem normalises paths itself and
 * would reject the worst of these anyway, but a guard that only holds because
 * a dependency happens to hold is not a guard — and the URL and voter uses have
 * no Flysystem in them at all.
 *
 * Static, because there is nothing to configure: these rules are the same in
 * every deployment.
 */
final class EvidenceKey
{
    /**
     * One path segment: letters, digits, dot, underscore, hyphen. Notably
     * absent are the space, the backslash and the colon — a Windows drive
     * ("C:/…") therefore fails on the colon rather than needing its own rule.
     */
    private const string SEGMENT = '/^[A-Za-z0-9._-]+$/';

    /** The suffix every generated preview carries. */
    public const string THUMB_SUFFIX = '.thumb.jpg';

    private function __construct()
    {
    }

    /**
     * Assemble a key from a caller's prefix, a caller's client key and an
     * extension derived from the DETECTED type.
     *
     * The prefix may carry stray leading/trailing slashes — callers build them
     * by concatenation and a doubled slash is a typo, not an attack. Everything
     * else must already be clean, and if it is not, nothing is stored.
     */
    public static function build(string $keyPrefix, string $clientKey, string $extension): string
    {
        $prefix = trim($keyPrefix, '/');
        if ('' === $prefix) {
            throw InvalidEvidenceKeyException::for($keyPrefix, 'a key needs an owning prefix');
        }
        self::assertValid($prefix);

        // The client half is attacker-controlled text and must be ONE segment:
        // a clientKey that could introduce a slash could write outside its own
        // prefix and so outside its own module's voter.
        if (!self::isSegment($clientKey)) {
            throw InvalidEvidenceKeyException::for($clientKey, 'a client key must be a single plain segment');
        }

        if (1 !== preg_match('/^[a-z0-9]+$/', $extension)) {
            throw InvalidEvidenceKeyException::for($extension, 'an extension must be lowercase alphanumeric');
        }

        return $prefix.'/'.$clientKey.'.'.$extension;
    }

    /** The preview that lives beside an original, named so it never needs its own column. */
    public static function thumb(string $key): string
    {
        return $key.self::THUMB_SUFFIX;
    }

    public static function isValid(string $key): bool
    {
        if ('' === $key) {
            return false;
        }

        foreach (explode('/', $key) as $segment) {
            if (!self::isSegment($segment)) {
                return false;
            }
        }

        return true;
    }

    /** @throws InvalidEvidenceKeyException */
    public static function assertValid(string $key): void
    {
        if (!self::isValid($key)) {
            throw InvalidEvidenceKeyException::for($key, 'it must be a relative path of plain segments');
        }
    }

    /**
     * The owning prefix, read back OUT of a stored key rather than remembered
     * alongside it — one source of truth, so a voter and a store can never
     * disagree about who owns a file.
     */
    public static function rootSegment(string $key): string
    {
        $slash = strpos($key, '/');

        return false === $slash ? $key : substr($key, 0, $slash);
    }

    private static function isSegment(string $segment): bool
    {
        // "." and ".." pass the charset test and must be excluded by name:
        // they are the entire traversal problem.
        if ('.' === $segment || '..' === $segment) {
            return false;
        }

        return 1 === preg_match(self::SEGMENT, $segment);
    }
}
