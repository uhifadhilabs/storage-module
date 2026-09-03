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

namespace Uhifadhi\Storage\Enum;

/**
 * Why an upload was refused.
 *
 * Callers map these onto their own transport: patrol turns each into a
 * PatrolApiException with the wording its API contract already promises, so
 * the field app keeps seeing the messages it was built against.
 */
enum RejectionReasonEnum: string
{
    /** The bytes did not arrive intact — a truncated or failed POST. */
    case UploadIncomplete = 'upload_incomplete';

    /** Bigger than this deployment accepts. */
    case TooLarge = 'too_large';

    /** The DETECTED type is not on the allowlist. Not a photograph. */
    case UnsupportedType = 'unsupported_type';
}
