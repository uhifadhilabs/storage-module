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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Uhifadhi\Storage\Model\Bytes;

/**
 * WHAT PHP ITSELF WILL ACCEPT — and a word to whoever can change it.
 *
 * There are two size limits on every upload and only one of them is this
 * module's. `storage.evidence.max_bytes` is the configured one; PHP's
 * `upload_max_filesize` and `post_max_size` are the real ceiling, applied before
 * a single line of this bundle runs. Where the ceiling is the LOWER of the two,
 * the deployment refuses evidence nobody chose to refuse — and it does so
 * through a truncated upload, which reads like a broken phone.
 *
 * SO THE MISCONFIGURATION SAYS SO IN THE LOG, at the moment an upload is
 * received, rather than waiting to be discovered by a ranger in the field. Not at
 * container compile: the ini a request runs under belongs to the web SAPI, and a
 * container compiled by the CLI (a deploy step, a cache warm-up) reads a
 * different php.ini than the one that will truncate the photograph. Not a console
 * command either — a module ships none.
 *
 * ONCE PER PROCESS. The fact is about the installation, not about the file, so
 * repeating it per upload would bury the lines that are about a file.
 */
final class ServerUploadLimitService
{
    private bool $said = false;

    public function __construct(
        private readonly ?LoggerInterface $logger,
        private readonly int $configuredMaxBytes,
        /**
         * The ceiling, for a test that must state one: `upload_max_filesize` is
         * PHP_INI_PERDIR and cannot be moved at runtime, so a suite can only
         * inject the number it wants to reason about.
         */
        private readonly ?int $serverMaxBytes = null,
    ) {
    }

    /**
     * THE ONE PLACE THIS NUMBER IS READ. Symfony computes it as
     * min(upload_max_filesize, post_max_size) — the two together are the ceiling,
     * because a body that busts the post limit never reaches the file limit — and
     * returns a float where it is bigger than PHP_INT_MAX.
     *
     * @see UploadedFile::getMaxFilesize()
     */
    public static function phpAccepts(): int
    {
        return (int) min(UploadedFile::getMaxFilesize(), \PHP_INT_MAX);
    }

    public function maxBytes(): int
    {
        return $this->serverMaxBytes ?? self::phpAccepts();
    }

    /**
     * The sentence names both numbers and both ini keys, because the person
     * reading a server log is the person who can raise them, and a warning they
     * have to go and measure is a warning they will skip.
     */
    public function warnIfNarrowerThanConfigured(): void
    {
        if ($this->said) {
            return;
        }

        $serverMaxBytes = $this->maxBytes();
        if ($serverMaxBytes >= $this->configuredMaxBytes) {
            return;
        }

        $this->said = true;
        $this->logger?->warning(\sprintf(
            'PHP accepts %s per upload, storage.evidence.max_bytes is %s — raise upload_max_filesize/post_max_size in php.ini to at least the configured cap, or evidence over PHP\'s ceiling is truncated before this module sees it.',
            Bytes::human($serverMaxBytes),
            Bytes::human($this->configuredMaxBytes),
        ));
    }
}
