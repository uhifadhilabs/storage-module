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

namespace Uhifadhi\Storage\Shell;

use Uhifadhi\Contracts\Shell\ConfigurationSection;
use Uhifadhi\Contracts\Shell\ConfigurationSectionsInterface;
use Uhifadhi\Storage\Controller\FilesConfigureController;
use Uhifadhi\Storage\Controller\FilesController;

/**
 * WHAT `Configure` OPENS IN THE FILES SECTION.
 *
 * FOUR SCREENS, NOT FOUR TEMPLATES. An area's configure sections are rendered
 * into the shell's own area-shaped page; an org-level section has no area in
 * its address, so its sections are screens of their own and the strip is built
 * from their routes. That is a difference of address, not of idiom.
 *
 * THE ORDER IS THE HOUSE'S, NOT THIS CLASS'S. The shell ranks a surface's
 * sections — Widget library first, Settings last, everything else in the order
 * it was declared — and the two entries in between are named `Storage targets`
 * and `Source modules` so the strip never repeats a tab word. The TAB is the
 * read (how full a target is, who put a file there); the SECTION is the edit.
 *
 * `Storage targets` IS THE SHIPPED "WHERE FILES GO" SCREEN, which already is
 * the storage editor. Declaring it here rather than drawing a second one is
 * the whole point of the sections contract.
 */
final readonly class FilesSectionConfiguration implements ConfigurationSectionsInterface
{
    public function slug(): string
    {
        return FilesSectionTabs::SURFACE;
    }

    public function heading(): string
    {
        return 'Files';
    }

    public function summary(): string
    {
        return 'How the hub behaves, what a file may be, and who may change either.';
    }

    public function sections(): array
    {
        return [
            ConfigurationSection::screen(
                ConfigurationSection::WIDGETS,
                'Widget library',
                FilesController::WIDGETS,
            ),
            ConfigurationSection::screen(
                'storage-targets',
                'Storage targets',
                FilesController::SETTINGS,
            ),
            ConfigurationSection::screen(
                'source-modules',
                'Source modules',
                FilesConfigureController::SOURCES,
            ),
            ConfigurationSection::screen(
                ConfigurationSection::SETTINGS,
                'Files settings',
                FilesConfigureController::SETTINGS,
            ),
        ];
    }
}
