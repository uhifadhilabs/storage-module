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

namespace Uhifadhi\Storage\Tests\Unit;

use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Bundle\ShellBundle\Test\VocabularyConformanceTestCase;

/**
 * THE FILES SCREENS SPEND NOTHING NOBODY SHIPS.
 *
 * THE CHAIN IS THE SHEETS A PAGE ACTUALLY LINKS, in the order templates/base.
 * html.twig links them: the shell's design system, the shell's widget library
 * sheet — the hub IS a widget dashboard, so every page of it links that one —
 * and then this bundle's own two, last because they are the ones allowed to
 * decorate.
 *
 * @see VocabularyConformanceTestCase
 */
final class VocabularyConformanceTest extends VocabularyConformanceTestCase
{
    protected static function bundlePath(): string
    {
        return \dirname(__DIR__, 2);
    }

    protected static function alias(): string
    {
        return 'storage';
    }

    /**
     * Two: the hub's own vocabulary, and the preview overlay's, which is this
     * bundle's one shareable component and is linked by another module's page
     * without files.css beside it.
     *
     * @return list<string>
     */
    protected static function ownStylesheets(): array
    {
        return ['files.css', 'preview.css'];
    }

    /** @return list<string> */
    protected static function linkedStylesheets(): array
    {
        return [
            ...parent::linkedStylesheets(),
            self::shellPublicDir().'/widget.css',
        ];
    }

    private static function shellPublicDir(): string
    {
        return \dirname(new \ReflectionClass(ShellBundle::class)->getFileName() ?: '').'/public';
    }
}
