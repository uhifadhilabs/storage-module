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
 * A key that is not a plain relative path inside the storage.
 *
 * This is a PROGRAMMER or ATTACKER error, never a user one, so it is an
 * \InvalidArgumentException: a caller that builds keys correctly can never see
 * it, and a request that provokes it should die where it stands.
 */
final class InvalidEvidenceKeyException extends \InvalidArgumentException
{
    public static function for(string $key, string $why): self
    {
        // The offending key is escaped into the message: it is untrusted text
        // and may end up in a log a human reads.
        return new self(\sprintf('"%s" is not a valid evidence key: %s.', addcslashes($key, "\0..\37\\\177"), $why));
    }
}
