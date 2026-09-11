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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A CONTROLLER MAY NOT ASSIGN TO A NAME STIMULUS ALREADY OWNS.
 *
 * `Controller` defines `scope`, `element`, `application`, `context`,
 * `identifier`, `targets`, `outlets`, `classes` and `data` as GETTERS, and
 * `dispatch` as a method. A Stimulus controller is an ES module and therefore
 * strict mode, so `this.scope = …` does not quietly shadow anything — it throws
 * a TypeError inside `connect()`, the controller never finishes connecting, and
 * the component is a box that draws perfectly and does nothing at all.
 *
 * That is exactly what shipped: the upload component rendered at the right size,
 * in the right grid, in both presentations, and swallowed every drop, with the
 * only evidence a line in the browser console. NOTHING IN A PHP SUITE CAN SEE
 * IT — there is no kernel, no request and no DOM involved, and the file is never
 * parsed by anything the build runs. So the check is the same shape as
 * {@see UploadSeamTest}: read the shipped asset as TEXT and refuse the
 * assignment.
 *
 * It covers EVERY controller this bundle ships, not only the one that had the
 * bug, because the next one will be written by somebody who never saw this.
 */
#[CoversNothing]
final class StimulusReservedNamesTest extends TestCase
{
    /**
     * Everything `@hotwired/stimulus`'s Controller puts on an instance. The
     * lifecycle methods are absent on purpose: `initialize`, `connect` and
     * `disconnect` are meant to be overridden, and overriding is a method
     * declaration rather than an assignment.
     *
     * @var list<string>
     */
    private const array RESERVED = [
        'application',
        'classes',
        'context',
        'data',
        'dispatch',
        'element',
        'identifier',
        'outlets',
        'scope',
        'targets',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function controllers(): iterable
    {
        // Every controller this bundle ships, found rather than listed: a
        // controller added later must be swept without anybody remembering to
        // add it here. An empty result fails the run by itself — PHPUnit errors
        // on a provider that yields nothing — which is the assertion that this
        // sweep is actually sweeping something.
        foreach (glob(\dirname(__DIR__, 3).'/assets/controllers/*_controller.js') ?: [] as $file) {
            yield basename($file) => [$file];
        }
    }

    #[DataProvider('controllers')]
    public function testNoControllerAssignsToANameStimulusOwns(string $file): void
    {
        $source = (string) file_get_contents($file);

        foreach (self::RESERVED as $name) {
            // `this.<name> =`, with any spacing, and not `==`/`===`: an equality
            // test against one of these is perfectly ordinary.
            self::assertSame(
                0,
                preg_match('/\bthis\s*\.\s*'.preg_quote($name, '/').'\s*=(?!=)/', $source),
                \sprintf(
                    '%s assigns to this.%s, which Stimulus defines as a getter — in strict mode that throws inside connect() and the controller never starts.',
                    basename($file),
                    $name,
                ),
            );
        }
    }

    /** The name the upload controller uses instead, so the rename cannot silently come back. */
    public function testTheUploadControllerKeepsItsOwnNameForTheGridItListensTo(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 3).'/assets/controllers/upload_controller.js');

        self::assertStringContainsString('this.uploadScope =', $source);
        self::assertStringContainsString('this.uploadScope.addEventListener', $source);
        self::assertStringContainsString('this.uploadScope.removeEventListener', $source);
    }
}
