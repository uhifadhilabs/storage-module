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
    /**
     * EVERY SCREEN THAT DRAWS ACTIONS, FOUND RATHER THAN LISTED — a hand-typed
     * list stops covering the screen added after it was typed, and this is a
     * rule about an idiom rather than about four files.
     *
     * A SCREEN WITH NO ACTIONS OF ITS OWN IS NOT AN OFFENDER. The frame writes
     * one Configure entry on every surface, so a configure section, a reading
     * tab and a register that adds nothing to it are all complete with an
     * empty right-hand end. {@see testSomeScreenStillDrawsActions} is what
     * keeps the absence from quietly becoming universal.
     *
     * @return iterable<string, array{string}>
     */
    public static function templates(): iterable
    {
        foreach (self::screens() as $name => $path) {
            if (null !== self::pageActionsBlockOf($path)) {
                yield $name => [$path];
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private static function screens(): array
    {
        $screens = [];
        foreach (glob(\dirname(__DIR__, 3).'/templates/files/*.html.twig') ?: [] as $path) {
            // A PARTIAL IS NOT A SCREEN. Only a page fills the frame's sockets.
            if (!str_starts_with(basename($path), '_')) {
                $screens[basename($path)] = $path;
            }
        }

        return $screens;
    }

    /**
     * The idiom has to be drawn SOMEWHERE, or a rule about how actions are
     * drawn passes on a section that draws none at all.
     */
    public function testSomeScreenStillDrawsActions(): void
    {
        self::assertNotSame([], iterator_to_array(self::templates()), 'No screen in this section draws a page action, so nothing above is being checked.');
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

        return self::pageActionsBlockOf($path) ?? '';
    }

    /** The block's body, or null for a screen that declares none. */
    private static function pageActionsBlockOf(string $path): ?string
    {
        $twig = (string) file_get_contents($path);
        preg_match('/{%\s*block shell_page_actions\s*%}(.*?){%\s*endblock\s*%}/s', $twig, $matches);

        return $matches[1] ?? null;
    }
}
