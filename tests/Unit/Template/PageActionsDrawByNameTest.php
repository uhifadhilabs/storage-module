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

namespace Uhifadhi\Storage\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;

/**
 * THE PAGE HEAD'S ACTIONS DRAW BY NAME, NOT BY PASTED PATH DATA.
 *
 * Every action in the shell's page head is one of a small set of controls a
 * person meets on every screen of the installation, so its mark has to be the
 * same mark everywhere. An inline `<svg>` is a copy of one: it cannot be
 * restyled with the set it came from, nothing checks it against the glyph the
 * shell actually ships, and two templates that pasted the same path at
 * different times drift apart without a single test noticing.
 *
 * A NAME IS CHECKABLE AND A PATH IS NOT. `ux_icon('shell:chevron-left')` is
 * held to a file by {@see \Uhifadhi\Storage\Tests\Unit\VocabularyConformanceTest}
 * — the prefix has to be one this bundle may use, and a name under this
 * bundle's own prefix has to resolve to a file it ships. Thirty-eight bytes of
 * `d="…"` are held to nothing.
 *
 * SCOPED TO THE PAGE-ACTION BLOCKS. This is the region the platform has settled
 * an idiom for, down to the class and the glyph — the widget-library link is
 * `<a class="tgl w-act" …>{{ ux_icon('shell:layout-grid') }} Widget library</a>`
 * on every module dashboard in the fleet. The drawings inside a widget's own
 * body are a separate argument with a separate diff.
 */
final class PageActionsDrawByNameTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function templates(): iterable
    {
        $dir = \dirname(__DIR__, 3).'/templates/files';

        foreach (['index', 'detail', 'widgets', 'settings'] as $name) {
            yield $name.'.html.twig' => [$dir.'/'.$name.'.html.twig'];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('templates')]
    public function testThePageActionsCarryNoInlineSvg(string $path): void
    {
        $actions = self::pageActionsBlock($path);

        self::assertStringNotContainsString('<svg', $actions, \sprintf(
            '%s draws an inline <svg> in its page actions. Name the glyph instead: '
            .'an existing shell: mark, or one shipped under this bundle\'s own storage: prefix.',
            basename($path),
        ));
    }

    /**
     * The page head's actions must be DRAWN, not merely free of pasted paths —
     * an assertion about an absence passes just as well on a block somebody
     * emptied.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('templates')]
    public function testThePageActionsDrawAtLeastOneNamedGlyph(string $path): void
    {
        self::assertMatchesRegularExpression(
            '/ux_icon\(\s*[\'"](?:shell|storage):/',
            self::pageActionsBlock($path),
            basename($path).' draws no named glyph in its page actions.',
        );
    }

    private static function pageActionsBlock(string $path): string
    {
        $twig = file_get_contents($path);
        self::assertIsString($twig, $path.' must ship.');

        preg_match('/{%\s*block shell_page_actions\s*%}(.*?){%\s*endblock\s*%}/s', $twig, $matches);

        if (!isset($matches[1])) {
            self::fail(basename($path).' declares no shell_page_actions block.');
        }

        return $matches[1];
    }
}
