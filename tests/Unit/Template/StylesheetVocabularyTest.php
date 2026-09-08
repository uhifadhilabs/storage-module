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
 * THE STYLESHEETS MUST BE SELF-CONTAINED IN THE DESIGN VOCABULARY THEY SPEND.
 *
 * files.css and preview.css are byte-faithful ports of the design's own
 * stylesheets, which speak DESIGN tokens (--fog, --dim, --ok, --warn…). The host
 * speaks rgb triplets (--c-fog, --c-ok…). Each shipped sheet carries a TOKEN
 * BRIDGE at its top that aliases the design names it uses to the host's names, so
 * the rules resolve on a host that never heard of the design tokens.
 *
 * A rule that spends a design token the bridge forgot to alias renders with a
 * BROKEN colour — `color: var(--ok)` with --ok undefined falls back to the
 * inherited colour, so a guard callout that should be green, a "no thumbnail"
 * pill that should be red and the danger button all quietly lose their meaning.
 * The bridge shipped without --ok/--warn/--fail and those exact rules broke on a
 * real installation. This test pins the whole class: no shipped sheet may spend a
 * design token it does not also define.
 *
 * And the generics the files templates lean on — the meta row (.rln) and its
 * colour helpers (.r/.d/.w) — live in the design's GLOBAL sheet, which the host
 * shell does not re-ship. The bundle must carry them itself, or every row on the
 * detail, index and settings pages collapses to an unspaced block. This test
 * pins that too.
 */
final class StylesheetVocabularyTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function stylesheets(): iterable
    {
        $public = \dirname(__DIR__, 3).'/public';

        yield 'files.css' => [$public.'/files.css'];
        yield 'preview.css' => [$public.'/preview.css'];
    }

    /**
     * Every design-named token a sheet SPENDS (var(--x), where x is neither a
     * host --c-* token nor a sheet-private --f-* token) must be DEFINED in that
     * same sheet's bridge.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheets')]
    public function testEveryDesignTokenSpentIsAlsoDefined(string $path): void
    {
        $css = (string) file_get_contents($path);

        preg_match_all('/var\(\s*--([a-z0-9-]+)\s*\)/i', $css, $used);
        preg_match_all('/--([a-z0-9-]+)\s*:/i', $css, $defined);

        $isDesignToken = static fn (string $name): bool => !str_starts_with($name, 'c-') && !str_starts_with($name, 'f-');

        $spent = array_unique(array_filter($used[1], $isDesignToken));
        $declared = array_unique(array_filter($defined[1], $isDesignToken));

        $missing = array_values(array_diff($spent, $declared));
        sort($missing);

        self::assertSame(
            [],
            $missing,
            \sprintf(
                '%s spends design token(s) [%s] its bridge never aliases — those rules render with a broken colour on the host.',
                basename($path),
                implode(', ', array_map(static fn (string $t): string => '--'.$t, $missing)),
            ),
        );
    }

    /**
     * The generics the design keeps in its global sheet, which the host shell
     * does not provide, and which the files templates depend on.
     */
    public function testFilesSheetShipsTheGenericsTheShellDoesNot(): void
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 3).'/public/files.css');

        // The meta row itself, as a base rule (not only the scoped .f-ovside .rln
        // override that already existed in preview.css).
        self::assertMatchesRegularExpression(
            '~(?:^|[};]|\*/)\s*\.rln\s*[,{:]~',
            $css,
            'files.css ships no base .rln rule — every row on the detail, index and settings pages depends on it.',
        );

        // The colour helpers the templates spell as `mono r`, standalone `d`
        // and `w`.
        foreach (['r', 'd', 'w'] as $helper) {
            self::assertMatchesRegularExpression(
                '~(?:^|[};]|\*/)\s*\.'.$helper.'\s*\{~',
                $css,
                \sprintf('files.css ships no .%s colour helper, which the files templates use.', $helper),
            );
        }
    }
}
