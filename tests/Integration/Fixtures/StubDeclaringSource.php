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

use Uhifadhi\Contracts\Storage\FileSourceInterface;

/**
 * A MODULE THAT SAYS IT STORES FILES AND HANDS NONE OVER — which is a real
 * state and not a broken one: a module installed this morning holds nothing
 * until somebody attaches something.
 *
 * It stands for the difference the Sources tab exists to draw. "We have that
 * module and it is empty" and "that module keeps no files" are different
 * answers, and only the declaration can tell them apart — so this fixture
 * implements the CORE's contract and not this bundle's supply interface, and
 * the hub must list it with no files rather than not at all.
 */
final class StubDeclaringSource implements FileSourceInterface
{
    public const string SLUG = 'permits';

    public function moduleSlug(): string
    {
        return self::SLUG;
    }

    public function fileWord(): string
    {
        return 'an application’s documents';
    }
}
