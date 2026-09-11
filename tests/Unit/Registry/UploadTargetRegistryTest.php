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

namespace Uhifadhi\Storage\Tests\Unit\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Storage\Registry\UploadTargetRegistry;
use Uhifadhi\Storage\Tests\Unit\Registry\Fixtures\RecordingUploadTarget;

/**
 * WHICH MODULE ANSWERS FOR A TARGET — and the deny-by-default that surrounds it.
 *
 * The component sends one string, "incident:0199…", and knows nothing else. What
 * turns that into a module is this registry, and the three ways it can fail all
 * resolve the same way: an unparseable string, a kind nobody claims and a kind
 * two modules claim are each "no target", because attaching a file to a record
 * nobody owns is worse than not attaching it.
 */
#[CoversClass(UploadTargetRegistry::class)]
final class UploadTargetRegistryTest extends TestCase
{
    public function testItReadsTheKindOffTheTargetString(): void
    {
        self::assertSame('incident', UploadTargetRegistry::kindOf('incident:0199abcd'));
        self::assertSame('0199abcd', UploadTargetRegistry::idOf('incident:0199abcd'));
    }

    /** A target with no record behind it — the design's `zone-import`. */
    public function testATargetMayBeAKindAlone(): void
    {
        self::assertSame('zone-boundary', UploadTargetRegistry::kindOf('zone-boundary'));
        self::assertSame('', UploadTargetRegistry::idOf('zone-boundary'));
    }

    /** A record id may itself carry a colon; only the FIRST one separates. */
    public function testOnlyTheFirstColonSeparates(): void
    {
        self::assertSame('incident', UploadTargetRegistry::kindOf('incident:a:b'));
        self::assertSame('a:b', UploadTargetRegistry::idOf('incident:a:b'));
    }

    public function testItFindsTheModuleThatClaimsTheKind(): void
    {
        $incidents = new RecordingUploadTarget('incident');
        $registry = new UploadTargetRegistry([new RecordingUploadTarget('observation'), $incidents]);

        self::assertSame($incidents, $registry->forTarget('incident:0199abcd'));
    }

    public function testAKindNobodyClaimsIsNoTarget(): void
    {
        $registry = new UploadTargetRegistry([new RecordingUploadTarget('observation')]);

        self::assertNull($registry->forTarget('incident:0199abcd'));
    }

    public function testAnEmptyRegistryAnswersForNothing(): void
    {
        self::assertNull(new UploadTargetRegistry([])->forTarget('incident:0199abcd'));
    }

    /**
     * Two modules claiming one kind is a wiring mistake, and answering with
     * whichever was tagged first would pick a module by accident.
     */
    public function testTwoModulesClaimingOneKindIsNoTarget(): void
    {
        $registry = new UploadTargetRegistry([new RecordingUploadTarget('incident'), new RecordingUploadTarget('incident')]);

        self::assertNull($registry->forTarget('incident:0199abcd'));
    }

    /**
     * REMOVAL ROUTES BY THE KEY'S OWN FIRST SEGMENT, which is the prefix the
     * upload wrote it under — the same read {@see \Uhifadhi\Storage\Service\EvidenceKey::rootSegment()}
     * already does for the permission contract, so a file can never be removed
     * through a module other than the one that received it.
     */
    public function testItRoutesAStoredKeyBackToTheModuleThatWroteIt(): void
    {
        $incidents = new RecordingUploadTarget('incident');
        $registry = new UploadTargetRegistry([$incidents]);

        self::assertSame($incidents, $registry->forKey('incident/0199abcd/ef12.jpg'));
        self::assertNull($registry->forKey('observation/0199abcd/ef12.jpg'));
    }

    public function testItListsTheKindsInstalledSoAHostCanSeeWhatIsWired(): void
    {
        $registry = new UploadTargetRegistry([new RecordingUploadTarget('observation'), new RecordingUploadTarget('incident')]);

        self::assertSame(['observation', 'incident'], $registry->kinds());
    }
}
