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

namespace UhifadhiLabs\Storage\Model;

/**
 * What a module writes down after an upload succeeded.
 *
 * Every field here is safe to persist and safe to move: the keys are RELATIVE,
 * so a deployment can switch from a local directory to S3 without rewriting a
 * single row.
 */
final readonly class StoredFile
{
    /**
     * @param string      $key      relative key of the original, inside the evidence storage
     * @param string      $mimeType the DETECTED type — never what the client claimed
     * @param int         $byteSize size of the original, in bytes
     * @param string|null $thumbKey relative key of the ~400px preview, or NULL when nothing
     *                              on this machine could decode the source (typically HEIC
     *                              without Imagick+libheif). Null means "there is no preview",
     *                              stated plainly, rather than a key pointing at nothing.
     */
    public function __construct(
        public string $key,
        public string $mimeType,
        public int $byteSize,
        public ?string $thumbKey = null,
    ) {
    }

    public function hasThumbnail(): bool
    {
        return null !== $this->thumbKey;
    }
}
