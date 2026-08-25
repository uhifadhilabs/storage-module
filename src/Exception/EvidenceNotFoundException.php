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

namespace UhifadhiLabs\Storage\Exception;

/**
 * The key is well-formed and the caller was entitled to it, but there is
 * nothing stored under it.
 *
 * Its own class so the serving route can answer 404 for this and 500 for a
 * genuine storage failure — two very different operational signals that a
 * single exception would blur.
 */
final class EvidenceNotFoundException extends \RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(\sprintf('No evidence is stored under "%s".', $key));
    }
}
