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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A SCREEN STATES FACTS IN FRAGMENTS. THE ARGUMENT GOES IN A COMMENT.
 *
 * RULED, and this is the rule with a number on it: a prose node on a Files
 * screen fits on ONE LINE at the width the workspace is measured at. Judged by
 * rendered height, that is about {@see LIMIT} characters — past it the node
 * wraps, and a wrapped explanation on a screen somebody opens forty times a
 * day is an essay whatever its author called it.
 *
 * THE BAR IS ON `.use`, WHICH IS THE PROSE CLASS. It is the one the house uses
 * for a card's closing sentence, and it is where every essay this section ever
 * grew was written. Two neighbours are deliberately NOT held to it:
 *
 *   `.sxlead`  a card's lead — the settled designs draw it as a run of short
 *              fragments on one band, and it is measured as a band rather
 *              than as a sentence.
 *   `.pghint`  the house page hint, which is the shell's markup and the
 *              shell's to measure.
 *
 * Holding those two to a sentence bar would not shorten the product; it would
 * delete components the designs draw. If the ruling is meant to reach them,
 * the bar belongs on their own rendered height and the designs change first.
 *
 * NOTHING HERE MEASURES A COMMENT. Twig comments are stripped before counting,
 * which is the point: an argument moved out of the markup and into a `{# … #}`
 * is the fix this test is asking for, not a way around it.
 */
final class NoEssaysTest extends TestCase
{
    /**
     * ONE LINE AT 1440px, IN CHARACTERS, AND THE NUMBER IS CALIBRATED RATHER
     * THAN CHOSEN.
     *
     * It was 110 on a first estimate of a half-page card at 12.5px. Three
     * fragments of the SETTLED design (workspace 49b65a0) then measured 111,
     * 120 and 126 — a full-width widget foot is wider than half a page, and
     * the estimate was tight rather than the design wrong. The bar is what
     * separates a fragment from an essay, so it is set above the longest
     * fragment the design ships and well below the shortest essay this
     * section ever grew: the twelve it replaced ran 136 to 334.
     *
     * RAISE IT ONLY AGAINST A SETTLED DESIGN, never to let a new sentence
     * through. A node that does not fit is a node to split.
     */
    public const int LIMIT = 130;

    /** The prose class the bar is on. */
    private const string PROSE = 'use';

    /** @return iterable<string, array{string}> */
    public static function screens(): iterable
    {
        foreach (glob(\dirname(__DIR__, 3).'/templates/files/*.html.twig') ?: [] as $path) {
            yield basename($path) => [$path];
        }
    }

    #[DataProvider('screens')]
    public function testNoProseNodeRunsPastOneLine(string $path): void
    {
        $offenders = [];
        foreach (self::proseOf($path) as $sentence) {
            if (mb_strlen($sentence) > self::LIMIT) {
                $offenders[] = \sprintf('%d chars: "%s"', mb_strlen($sentence), $sentence);
            }
        }

        self::assertSame([], $offenders, \sprintf(
            "%s writes a prose node longer than one line (%d characters). State it as fragments and put the argument in a Twig comment.\n%s",
            basename($path),
            self::LIMIT,
            implode("\n", $offenders),
        ));
    }

    /**
     * THE RULE HAS TO HAVE SOMETHING TO BITE ON. A section that answered by
     * deleting every sentence would pass this file trivially, and the next
     * author would reintroduce one against no test at all.
     */
    public function testTheSectionStillCarriesProseWorthMeasuring(): void
    {
        $found = 0;
        foreach (self::screens() as [$path]) {
            $found += \count(self::proseOf($path));
        }

        self::assertGreaterThan(0, $found, 'no screen writes a prose node, so nothing above is being checked.');
    }

    /**
     * Every `.use` node's visible text, with markup, Twig tags and Twig
     * comments removed and whitespace collapsed — which is what a reader
     * actually meets on the line.
     *
     * A `{{ value }}` counts as one character rather than nothing: a node that
     * only fits because its data is absent at test time is a node that wraps
     * in the park, and this test exists to be right about the park.
     *
     * @return list<string>
     */
    private static function proseOf(string $path): array
    {
        $twig = (string) file_get_contents($path);
        $twig = (string) preg_replace('/\{#.*?#\}/s', '', $twig);

        preg_match_all(
            '/<(p|div|span)[^>]*class="[^"]*\b'.self::PROSE.'\b[^"]*"[^>]*>(.*?)<\/\1>/s',
            $twig,
            $matches,
        );

        $prose = [];
        foreach ($matches[2] as $body) {
            $text = (string) preg_replace('/\{[{%].*?[%}]\}/s', 'x', $body);
            $text = (string) preg_replace('/<[^>]+>/', ' ', $text);
            $text = trim((string) preg_replace('/\s+/', ' ', html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5)));

            if ('' !== $text) {
                $prose[] = $text;
            }
        }

        return $prose;
    }
}
