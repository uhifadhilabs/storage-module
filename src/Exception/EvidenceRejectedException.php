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

use Uhifadhi\Storage\Enum\RejectionReasonEnum;
use Uhifadhi\Storage\Model\Bytes;

/**
 * The file is not acceptable evidence. Retrying it unchanged will fail again,
 * which is exactly what a field app needs to know: patrol maps this to a 4xx
 * with retryable=false so the handset stops trying and tells the ranger.
 */
final class EvidenceRejectedException extends \RuntimeException
{
    /**
     * @param array<string, scalar|null> $details
     */
    public function __construct(
        public readonly RejectionReasonEnum $reason,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function uploadIncomplete(?string $why): self
    {
        return new self(
            RejectionReasonEnum::UploadIncomplete,
            'That upload did not arrive intact.',
            ['reason' => $why],
        );
    }

    /**
     * PHP ITSELF REFUSED IT, at `upload_max_filesize` / `post_max_size`, and
     * handed over a truncated file. The same distinction
     * {@see UploadRefusedException::forFailedUpload()}
     * draws, made here too because a caller validating before it uploads reads
     * this class and would otherwise be told a transfer had broken.
     */
    public static function exceedsServerLimit(int $serverMaxBytes): self
    {
        return new self(
            RejectionReasonEnum::ExceedsServerLimit,
            \sprintf('That file is larger than this server accepts (%s).', Bytes::human($serverMaxBytes)),
            ['serverMaxBytes' => $serverMaxBytes],
        );
    }

    public static function tooLarge(int $byteSize, int $maxBytes): self
    {
        return new self(
            RejectionReasonEnum::TooLarge,
            'That file is larger than this deployment accepts.',
            ['byteSize' => $byteSize, 'maxBytes' => $maxBytes],
        );
    }

    /**
     * $accepts is what the refusing allowlist DOES take, in words — "a
     * photograph", "a GPX track", "a photograph, document or GPX track". It is
     * passed in rather than written here because only the allowlist knows, and a
     * sentence that named photographs while the deployment took tracks would be
     * a message the guard could not keep.
     */
    public static function unsupportedType(?string $mimeType, string $accepts = 'a kind this deployment accepts'): self
    {
        return new self(
            RejectionReasonEnum::UnsupportedType,
            \sprintf('That file is not %s.', $accepts),
            ['mimeType' => $mimeType],
        );
    }

    /**
     * On the allowlist, yet nothing knows a file extension for it. The same
     * reason as unsupportedType() deliberately: to a caller this is still
     * "that type cannot be stored here, and retrying will not change it", and
     * a new reason would need a mapping in every module that already answers
     * these three.
     */
    public static function unnameableType(string $mimeType): self
    {
        return new self(
            RejectionReasonEnum::UnsupportedType,
            'This deployment allows that type, but no file extension is known for it.',
            ['mimeType' => $mimeType],
        );
    }
}
