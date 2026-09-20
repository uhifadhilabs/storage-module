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

namespace Uhifadhi\Storage\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A PAGE THAT HAS SOMETHING TO EXPLAIN SAYS IT ONCE, AT THE BOTTOM.
 *
 * RULED. The hub used to open with two cards of prose above its register,
 * and the design deleted them: a reader comes to a register to FIND
 * something, and the explanation is what they want after they have failed
 * to. So the register keeps nothing, and the one hint these pages carry sits
 * at the foot of the Storage tab.
 *
 * THE MARKUP IS THE SHELL'S. This module drew its own near-copy of the same
 * card — dashed accent border, info glyph, bold lead — under its own name,
 * and it was the fifth such copy in the fleet. The shell ships one partial;
 * a module includes it or has no hint.
 */
final class PageHintTest extends FilesTestCase
{
    /**
     * EVERY FILES PAGE, and what it is allowed to carry. A list rather than a
     * glob, because "which pages have a hint" is the ruling itself: a page
     * added later that wants one is a decision somebody makes, not a default
     * it inherits.
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function pages(): iterable
    {
        yield 'overview' => ['/files/overview', false];
        yield 'register' => ['/files', false];
        yield 'sources' => ['/files/sources', false];
        yield 'storage' => ['/files/storage', true];
        yield 'files settings' => ['/files/configure', false];
        yield 'source modules' => ['/files/configure/sources', false];
        yield 'storage targets' => ['/files/settings', true];
    }

    /** ONE PER PAGE, OR NONE. Two hints is an essay in instalments. */
    #[DataProvider('pages')]
    public function testAPageCarriesAtMostOneHintAndOnlyWhereItWasRuled(string $path, bool $carries): void
    {
        $hints = $this->open($path)->filter('.pghint');

        self::assertCount($carries ? 1 : 0, $hints, $path);
    }

    /**
     * THE REGISTER KEEPS NOTHING, stated on its own because it is the page
     * the ruling was about: its two prose cards went, then its lead went, and
     * nothing replaced either.
     */
    public function testTheRegisterKeepsNothing(): void
    {
        $register = $this->open('/files');

        self::assertCount(0, $register->filter('.pghint'));
        self::assertCount(0, $register->filter('.f-say'), 'the module draws no hint of its own, under any name');
    }

    /** And the Storage tab carries the words the owner ruled, verbatim. */
    public function testTheStorageTabCarriesTheOneHint(): void
    {
        $hint = $this->open('/files/storage')->filter('.pghint');

        self::assertCount(1, $hint);
        self::assertStringContainsString('Every file belongs to a record', $hint->text());
        self::assertStringContainsString('the owning record’s answer', $hint->text());
        self::assertStringContainsString('never overrules', $hint->text());
    }

    /**
     * IT IS AT THE BOTTOM, which is the whole of the ruling: above the
     * content it is read by everybody on every visit forever, and below it
     * is read by the person still wondering.
     */
    public function testTheHintIsTheLastThingOnThePage(): void
    {
        $body = $this->open('/files/storage')->filter('.pgbody')->html();

        $hint = strpos($body, 'pghint');
        self::assertIsInt($hint);

        // AFTER THE LAST CARD. Measured against the page's own content rather
        // than against the end of the markup: the frame appends the file
        // preview overlay after every page body, and that is furniture rather
        // than something the hint should sit above.
        $lastCard = strrpos($body, '<div class="c');
        self::assertIsInt($lastCard);
        self::assertGreaterThan($lastCard, $hint, 'the hint sits under the thing it is about, not above it');
    }

    private function open(string $path): Crawler
    {
        $client = $this->warden(static::createClient());
        $crawler = $client->request('GET', $path);

        self::assertResponseIsSuccessful($path);

        return $crawler;
    }
}
