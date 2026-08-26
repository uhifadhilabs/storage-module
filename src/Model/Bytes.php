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
 * How many bytes, said the way a warden says it.
 *
 * "3.9 MB", "61.4 GB", "412 KB" — one decimal from megabytes up, none below,
 * because a person deciding whether to buy more disk needs the magnitude and
 * nobody has ever needed the last three digits. Decimal units (1000, not 1024):
 * the number on the invoice for a terabyte of object storage is a decimal
 * terabyte, and the settings page has to agree with the invoice.
 */
final class Bytes
{
    private const array UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

    public static function human(int $bytes): string
    {
        $bytes = max(0, $bytes);
        $unit = 0;
        $value = (float) $bytes;

        while ($value >= 1000 && $unit < \count(self::UNITS) - 1) {
            $value /= 1000;
            ++$unit;
        }

        // Bytes and kilobytes are whole numbers; from megabytes up, one decimal
        // — "3.9 MB" carries information, "3.94827 MB" carries noise.
        $decimals = $unit >= 2 ? 1 : 0;

        return number_format($value, $decimals).' '.self::UNITS[$unit];
    }

    /**
     * The number and its unit apart, for the KPI card that draws the unit small.
     *
     * @return array{string, string}
     */
    public static function split(int $bytes): array
    {
        $parts = explode(' ', self::human($bytes));

        return [$parts[0], $parts[1] ?? 'B'];
    }
}
