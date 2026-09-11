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

namespace Uhifadhi\Storage\Tests\Integration\Fixtures;

/**
 * A RECORD A FILE CAN BE ATTACHED TO, standing in for whatever a module's is.
 *
 * The contract types it as a plain `object` and the storage never looks inside
 * one; this class exists so the suite can prove that, by being a class the
 * bundle has never heard of.
 */
final readonly class StubUploadRecord
{
    public function __construct(public string $id)
    {
    }
}
