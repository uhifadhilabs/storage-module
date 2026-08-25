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
 * The store itself failed — a full disk, a transient mount, an S3 endpoint
 * that timed out.
 *
 * Distinct from EvidenceRejectedException on purpose, and the distinction is
 * the whole value: this one IS worth retrying. A full disk is not the phone's
 * fault, and the photograph still exists on the handset.
 */
final class EvidenceStorageFailedException extends \RuntimeException
{
    public static function whileWriting(string $key, \Throwable $previous): self
    {
        return new self(\sprintf('The evidence store refused to write "%s".', $key), 0, $previous);
    }

    public static function whileReading(string $key, \Throwable $previous): self
    {
        return new self(\sprintf('The evidence store refused to read "%s".', $key), 0, $previous);
    }

    public static function unreadableSource(string $path): self
    {
        return new self(\sprintf('The uploaded file at "%s" could not be opened for reading.', $path));
    }
}
