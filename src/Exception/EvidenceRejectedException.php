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

    public static function tooLarge(int $byteSize, int $maxBytes): self
    {
        return new self(
            RejectionReasonEnum::TooLarge,
            'That file is larger than this deployment accepts.',
            ['byteSize' => $byteSize, 'maxBytes' => $maxBytes],
        );
    }

    public static function unsupportedType(?string $mimeType): self
    {
        return new self(
            RejectionReasonEnum::UnsupportedType,
            'That file is not a photograph.',
            ['mimeType' => $mimeType],
        );
    }
}
