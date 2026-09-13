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
 * EVERY PRINTED INSTANT IS READ IN THE READER'S OWN TIMEZONE.
 *
 * A file is recorded by a handset in the field, stored as UTC and printed by a
 * server that runs in one zone. Printed straight, the wall-clock a warden reads
 * is the server's, not hers. The frame fixes that for every module at once: it
 * localises every `<time datetime>` on the page from the machine attribute. So
 * the only thing a template must do is emit the semantic element —
 *
 *     <time datetime="{{ file.arrivedAt|date('c') }}" data-localtime-format="daystamp">…</time>
 *
 * — whose text is the server's UTC reading and stays correct with no JavaScript.
 *
 * A bare `{{ x|date('D j M Y') }}` is the defect this pins: it renders, it looks
 * right to whoever wrote it, and it is wrong for every reader outside the
 * server's zone. Nothing but a text scan catches it, because every rendered page
 * is a perfectly valid page.
 *
 * The shapes are the frame's, and a template asks for the one the design draws
 * for that element: `stamp`, `daystamp`, `clock`, `day`, `daylong`, or the
 * verbose `datetime` / `date` / `time`.
 *
 * A CALENDAR DAY IS NOT AN INSTANT, and is never localised. The day a group of
 * files is filed under is a key the server grouped by; shifted into another zone
 * it would name a different day from the one the rows underneath it belong to.
 * Such a `<time>` carries a date-only `datetime` and asks for NO shape, which is
 * the frame's own signal to leave it alone:
 *
 *     <time datetime="{{ group.day|date('Y-m-d') }}">wed 19 aug 2026</time>
 *
 * So the rule this pins has two halves: a full instant needs a shape, and a
 * date-only value must not have one.
 */
final class LocalTimeIdiomTest extends TestCase
{
    /** The shapes the frame's scanner answers to. A template may ask for no other. */
    private const array SHAPES = ['stamp', 'daystamp', 'clock', 'day', 'daylong', 'datetime', 'date', 'time'];

    /**
     * The one macro that fills a DATA CONTRACT instead of drawing markup.
     *
     * The overlay's side panel is written by preview_controller.js, so what the
     * template hands it is attributes: the machine instant, and the server's
     * reading as the text to put inside the `<time>` the controller builds. The
     * instant is localised one step later, by the same scanner, which is what
     * testTheOverlayWritesTheSameIdiomItsTemplateDoes holds. Everything else in
     * that file is ordinary markup and is scanned like any other template.
     */
    private const array CONTRACT_MACROS = ['overlay/_preview.html.twig' => 'entry'];

    /** @return iterable<string, array{string, string}> */
    public static function templates(): iterable
    {
        $root = \dirname(__DIR__, 3).'/templates';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            if ('twig' !== $file->getExtension()) {
                continue;
            }
            $name = str_replace(\DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), \strlen($root) + 1));
            yield $name => [$name, $file->getPathname()];
        }
    }

    #[DataProvider('templates')]
    public function testEveryPrintedInstantIsLocalisedByTheFrame(string $name, string $path): void
    {
        $markup = self::withoutComments((string) file_get_contents($path));
        if (isset(self::CONTRACT_MACROS[$name])) {
            $markup = self::withoutMacro($markup, self::CONTRACT_MACROS[$name]);
        }
        $spans = self::timeElements($markup);
        $offenders = [];

        preg_match_all('/\|\s*date\s*\(/', $markup, $prints, \PREG_OFFSET_CAPTURE);
        foreach ($prints[0] as [, $at]) {
            if (!self::within($at, $spans)) {
                $offenders[] = 'printed outside <time datetime=…>: '.trim(substr($markup, max(0, $at - 60), 120));
            }
        }

        preg_match_all('/<time\b[^>]*>/s', $markup, $tags);
        foreach ($tags[0] as $tag) {
            if (1 !== preg_match('/\sdatetime="([^"]*)"/', $tag, $value)) {
                $offenders[] = 'no datetime attribute: '.$tag;
                continue;
            }

            $named = 1 === preg_match('/\sdata-localtime-format="([^"]+)"/', $tag, $shape);

            if (self::isCalendarDay($value[1])) {
                if ($named) {
                    $offenders[] = 'a calendar day asking to be localised: '.$tag;
                }
                continue;
            }

            if (!$named) {
                $offenders[] = 'an instant naming no shape: '.$tag;
            } elseif (!\in_array($shape[1], self::SHAPES, true)) {
                $offenders[] = 'a shape the frame does not answer to: '.$shape[1];
            }
        }

        self::assertSame(
            [],
            $offenders,
            \sprintf('%s reads instants in the server\'s timezone, not the reader\'s.', $name),
        );
    }

    /**
     * The overlay's side panel is written by the preview controller after the
     * page loaded, so its rows carry the same two attributes or they are never
     * localised — the frame's scanner watches for exactly this markup.
     */
    public function testTheOverlayWritesTheSameIdiomItsTemplateDoes(): void
    {
        $js = (string) file_get_contents(\dirname(__DIR__, 3).'/assets/controllers/preview_controller.js');
        $partial = (string) file_get_contents(\dirname(__DIR__, 3).'/templates/overlay/_preview.html.twig');

        self::assertStringContainsString('<time datetime="', $js, 'preview_controller.js must write the element the frame localises.');
        self::assertStringContainsString('data-localtime-format="', $js, 'preview_controller.js must name the shape it wants.');

        foreach (['data-f-taken-at', 'data-f-uploaded-at'] as $attribute) {
            self::assertStringContainsString($attribute, $partial, \sprintf('the contract must carry %s.', $attribute));
            self::assertStringContainsString(substr($attribute, \strlen('data-f-')), $js, \sprintf('preview_controller.js must read %s.', $attribute));
        }
    }

    /** @param list<array{int, int}> $spans */
    private static function within(int $at, array $spans): bool
    {
        foreach ($spans as [$from, $to]) {
            if ($at >= $from && $at < $to) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{int, int}> */
    private static function timeElements(string $markup): array
    {
        preg_match_all('/<time\b.*?<\/time>/s', $markup, $matches, \PREG_OFFSET_CAPTURE);

        $spans = [];
        foreach ($matches[0] as [$element, $at]) {
            $spans[] = [$at, $at + \strlen($element)];
        }

        return $spans;
    }

    /**
     * A date-only `datetime` — either printed with the `Y-m-d` format or written
     * as a literal day. Anything carrying a clock is an instant.
     */
    private static function isCalendarDay(string $value): bool
    {
        return 1 === preg_match('/^\{\{[^}]*\|\s*date\s*\(\s*[\'"]Y-m-d[\'"]\s*\)[^}]*\}\}$/', trim($value))
            || 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value));
    }

    /** One macro's body, taken out of the scan. */
    private static function withoutMacro(string $twig, string $macro): string
    {
        return (string) preg_replace(
            \sprintf('/\{%%-?\s*macro\s+%s\s*\(.*?\{%%-?\s*endmacro\s*-?%%\}/s', preg_quote($macro, '/')),
            '',
            $twig,
        );
    }

    /** Twig comments are not markup: a design note may spell a date any way it likes. */
    private static function withoutComments(string $twig): string
    {
        return (string) preg_replace('/\{#.*?#\}/s', '', $twig);
    }
}
