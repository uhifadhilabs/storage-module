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

namespace Uhifadhi\Storage\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Uhifadhi\Storage\Model\Bytes;

/**
 * The two things the Files templates cannot say for themselves.
 *
 * Kept to formatting only. A Twig extension is a tempting place to put the
 * decisions a template should not be making, and every one of those on this
 * surface — what a file is, who owns it, whether it may be removed — belongs to
 * the registry or to the owning module instead.
 */
final class FilesExtension extends AbstractExtension
{
    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('storage_bytes', Bytes::human(...)),
            new TwigFilter('storage_bytes_split', Bytes::split(...)),
        ];
    }
}
