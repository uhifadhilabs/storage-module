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

namespace UhifadhiLabs\Storage\Tests\Unit\Registry;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use UhifadhiLabs\Storage\Enum\FileKindEnum;
use UhifadhiLabs\Storage\Enum\GuardStateEnum;
use UhifadhiLabs\Storage\Enum\ThumbStateEnum;
use UhifadhiLabs\Storage\Model\FileEntry;
use UhifadhiLabs\Storage\Model\FileFilter;
use UhifadhiLabs\Storage\Model\FileGuard;
use UhifadhiLabs\Storage\Registry\FileRegistry;
use UhifadhiLabs\Storage\Registry\FileSourceInterface;

/**
 * The cross-module aggregation, on its own.
 *
 * THE SAMPLE. Two modules, five files, ten days apart, chosen so every grouping
 * the hub draws has at least two rows and at least one tie to break:
 *
 *   quadrat  QDR-1  photo  4 MB   taken 21 aug, arrived 21 aug   thumbnail made
 *   quadrat  QDR-1  photo  3 MB   taken 21 aug, arrived 21 aug   waiting
 *   quadrat  QDR-2  track  1 MB   taken 19 aug, arrived 19 aug   nothing to shrink
 *   ledger   LDG-9  photo  6 MB   taken 19 aug, arrived 20 aug   failed
 *   ledger   LDG-9  doc    2 MB   no taken day, arrived 12 aug   nothing to shrink
 *
 * "Now" is fixed at 22 aug 2026 throughout, so "arrived this week" is a fact
 * about the sample rather than about the day the suite runs.
 */
final class FileRegistryTest extends TestCase
{
    private const string NOW = '2026-08-22 09:00:00';

    public function testItAggregatesEverySourceIntoOneNewestFirstList(): void
    {
        $keys = array_map(static fn (FileEntry $f): string => $f->key, self::registry()->all());

        self::assertSame(
            ['quadrat/qdr-1/b.jpg', 'quadrat/qdr-1/a.jpg', 'ledger/ldg-9/big.jpg', 'quadrat/qdr-2/t.gpx', 'ledger/ldg-9/form.pdf'],
            $keys,
            'the hub is ordered by the day a file was TAKEN, newest first, and inside a day by the moment it was taken',
        );
    }

    public function testASourceThatThrowsIsSkippedRatherThanFatal(): void
    {
        $registry = new FileRegistry([new BrokenSource(), ...self::sources()]);

        self::assertCount(5, $registry->all(), 'one module having a bad day must not hide every other module’s files');
    }

    public function testAnEmptyPlatformIsAnEmptyHubAndNotAnError(): void
    {
        $registry = new FileRegistry([]);

        self::assertSame([], $registry->all());
        self::assertSame(['files' => 0, 'bytes' => 0, 'made' => 0, 'waiting' => 0, 'failed' => 0, 'arrived' => 0], $registry->counts(null, self::now()));
        self::assertSame([], $registry->modules());
    }

    public function testTheFourCountsAreCountedFromTheRowsThemselves(): void
    {
        // 4+3+1+6+2 MB, one thumbnail made, one waiting, one failed, and four of
        // the five arrived inside the seven days before 22 aug (the pdf, 12 aug,
        // did not).
        self::assertSame(
            ['files' => 5, 'bytes' => 16_000_000, 'made' => 1, 'waiting' => 1, 'failed' => 1, 'arrived' => 4],
            self::registry()->counts(null, self::now()),
        );
    }

    /**
     * @param array<string, string> $query
     * @param list<string>          $expected
     */
    #[DataProvider('filters')]
    public function testEveryChipIsOneQueryParameterAndNothingMore(array $query, array $expected): void
    {
        $kept = array_map(
            static fn (FileEntry $f): string => $f->key,
            self::registry()->filter(FileFilter::fromQuery($query)),
        );

        self::assertSame($expected, $kept);
    }

    /**
     * @return iterable<string, array{array<string, string>, list<string>}>
     */
    public static function filters(): iterable
    {
        yield 'nothing pressed keeps everything' => [
            [],
            ['quadrat/qdr-1/b.jpg', 'quadrat/qdr-1/a.jpg', 'ledger/ldg-9/big.jpg', 'quadrat/qdr-2/t.gpx', 'ledger/ldg-9/form.pdf'],
        ];
        yield 'module' => [
            ['module' => 'ledger'],
            ['ledger/ldg-9/big.jpg', 'ledger/ldg-9/form.pdf'],
        ];
        yield 'area' => [
            ['area' => 'east'],
            ['ledger/ldg-9/big.jpg', 'ledger/ldg-9/form.pdf'],
        ];
        yield 'kind' => [
            ['kind' => 'track'],
            ['quadrat/qdr-2/t.gpx'],
        ];
        yield 'the day the HANDSET recorded, not the day it arrived' => [
            ['day' => '2026-08-19'],
            ['ledger/ldg-9/big.jpg', 'quadrat/qdr-2/t.gpx'],
        ];
        yield 'has a small picture' => [
            ['thumb' => 'made'],
            ['quadrat/qdr-1/a.jpg'],
        ];
        yield 'has none' => [
            ['thumb' => '!made'],
            ['quadrat/qdr-1/b.jpg', 'ledger/ldg-9/big.jpg', 'quadrat/qdr-2/t.gpx', 'ledger/ldg-9/form.pdf'],
        ];
        yield 'search finds a file by its name' => [
            ['q' => 'form'],
            ['ledger/ldg-9/form.pdf'],
        ];
        yield 'search finds a file by its OWNING RECORD' => [
            ['q' => 'qdr-1'],
            ['quadrat/qdr-1/b.jpg', 'quadrat/qdr-1/a.jpg'],
        ];
        yield 'search does NOT read captions — a caption is the record’s' => [
            ['q' => 'swamp'],
            [],
        ];
        yield 'two chips narrow together' => [
            ['module' => 'quadrat', 'kind' => 'photo'],
            ['quadrat/qdr-1/b.jpg', 'quadrat/qdr-1/a.jpg'],
        ];
        yield 'a filter nobody can read is ignored, never fatal' => [
            ['kind' => 'nonsense', 'day' => 'last tuesday'],
            ['quadrat/qdr-1/b.jpg', 'quadrat/qdr-1/a.jpg', 'ledger/ldg-9/big.jpg', 'quadrat/qdr-2/t.gpx', 'ledger/ldg-9/form.pdf'],
        ];
    }

    public function testFilesAreGroupedUnderTheRecordThatOwnsThemNotUnderTheirName(): void
    {
        $groups = self::registry()->byOwner();

        self::assertSame(['quadrat/QDR-1', 'ledger/LDG-9', 'quadrat/QDR-2'], array_column($groups, 'ref'));
        self::assertCount(2, $groups[0]['files']);
        self::assertCount(2, $groups[1]['files'], 'a record’s files stay together even when one has no taken day');
    }

    public function testARecordIdIsOnlyUniqueInsideItsOwnModule(): void
    {
        $registry = new FileRegistry([
            new StubSource('alpha', 'Alpha', [self::file('alpha/x.jpg', 'SAME-1', 'alpha')]),
            new StubSource('beta', 'Beta', [self::file('beta/x.jpg', 'SAME-1', 'beta')]),
        ]);

        self::assertCount(2, $registry->byOwner(), 'two modules using the same record id are two records');
    }

    public function testDaysAreTheHandsetsAndADocumentFallsUnderTheDayItWasFiled(): void
    {
        self::assertSame(['2026-08-21', '2026-08-19', '2026-08-12'], self::registry()->days());
    }

    public function testModulesAreListedWithTheirRecordAndFileCounts(): void
    {
        $modules = self::registry()->modules();

        self::assertSame(['quadrat', 'ledger'], array_column($modules, 'slug'), 'ordered by what they cost');
        self::assertSame(['records' => 2, 'files' => 3, 'bytes' => 8_000_000], array_intersect_key($modules[0], array_flip(['records', 'files', 'bytes'])), 'QDR-1 and QDR-2 are two records');
        self::assertSame(1, $modules[1]['records'], 'both of the ledger’s files hang off LDG-9');
    }

    public function testAModuleThatIsInstalledButHoldsNothingIsStillListed(): void
    {
        $registry = new FileRegistry([new StubSource('permits', 'Permits', [])]);

        self::assertSame(
            [['slug' => 'permits', 'label' => 'Permits', 'attachesTo' => 'a record’s files', 'records' => 0, 'files' => 0, 'bytes' => 0]],
            $registry->modules(),
            '“we have that module and it is empty” is a different fact from “we do not have it”',
        );
    }

    public function testJustArrivedIsOrderedByArrivalAndEverythingElseByTheDayItWasTaken(): void
    {
        $recent = array_map(static fn (FileEntry $f): string => $f->key, self::registry()->recent(3));

        self::assertSame(['quadrat/qdr-1/b.jpg', 'quadrat/qdr-1/a.jpg', 'ledger/ldg-9/big.jpg'], $recent);
        self::assertSame(
            'ledger/ldg-9/big.jpg',
            self::registry()->recent(3)[2]->key,
            'the 19 aug photograph arrived on the 20th, which is why it is here and not under the 19th',
        );
    }

    public function testTheBiggestFilesAreTheBiggestOriginals(): void
    {
        self::assertSame(
            ['ledger/ldg-9/big.jpg', 'quadrat/qdr-1/a.jpg'],
            array_map(static fn (FileEntry $f): string => $f->key, self::registry()->biggest(2)),
        );
    }

    public function testWhatIsWaitingForASmallPictureIsTheQueueAndTheFailuresAndNothingElse(): void
    {
        $waiting = array_map(static fn (FileEntry $f): string => $f->key, self::registry()->withoutThumbnail());

        self::assertSame(['quadrat/qdr-1/b.jpg', 'ledger/ldg-9/big.jpg'], $waiting);
        self::assertNotContains('quadrat/qdr-2/t.gpx', $waiting, 'a track has nothing to shrink and is not waiting for anything');
    }

    public function testSharesAreOfTheSetHandedIn(): void
    {
        $kinds = self::registry()->byKind();

        self::assertSame([FileKindEnum::Photo, FileKindEnum::Document, FileKindEnum::Track], array_column($kinds, 'kind'));
        self::assertSame([60.0, 20.0, 20.0], array_column($kinds, 'share'));
    }

    public function testSpaceIsCountedByModuleAndByArea(): void
    {
        self::assertSame([50.0, 50.0], array_column(self::registry()->bySpace(), 'share'));
        self::assertSame(['west', 'east'], array_column(self::registry()->byArea(), 'slug'));
    }

    public function testArrivalsAreBucketedByTheWeekAFileReachedUs(): void
    {
        $weeks = self::registry()->arrivalsByWeek(3, null, self::now());

        self::assertCount(3, $weeks);
        self::assertSame(['2026-08-03', '2026-08-17'], [$weeks[0]['week']->format('Y-m-d'), $weeks[2]['week']->format('Y-m-d')]);
        self::assertSame([0, 1, 4], array_column($weeks, 'files'), 'the pdf arrived on the 12th; everything else in the week of the 17th');
        self::assertSame([0.0, 25.0, 100.0], array_column($weeks, 'share'), 'the bars are shares of the busiest week, not of the total');
    }

    public function testTheOwningModuleIsFoundByAskingItRatherThanByWalkingEveryFile(): void
    {
        $registry = self::registry();

        self::assertSame('ledger', $registry->sourceFor('ledger/ldg-9/big.jpg')?->moduleSlug());
        self::assertNull($registry->sourceFor('nobody/at/all.jpg'));
    }

    public function testTheGuardIsTheOwningModulesAnswer(): void
    {
        $guard = self::registry()->guard('quadrat/qdr-1/a.jpg', null);

        self::assertSame(GuardStateEnum::Allowed, $guard->state);
        self::assertSame('quadrat says so', $guard->title);
    }

    public function testAKeyNoModuleClaimsIsLockedRatherThanDenied(): void
    {
        $guard = self::registry()->guard('nobody/at/all.jpg', null);

        self::assertSame(GuardStateEnum::Locked, $guard->state);
        self::assertFalse($guard->offersRemoval(), 'nothing installed can authorise removing a file nothing installed owns');
    }

    public function testAVoterThatThrowsIsARefusalAndNotAGrant(): void
    {
        $registry = new FileRegistry([new BrokenSource()]);

        self::assertSame(GuardStateEnum::Locked, $registry->guard('broken/x.jpg', null)->state);
    }

    public function testTheRestOfARecordsFilesExcludesTheFileItself(): void
    {
        $registry = self::registry();
        $file = $registry->find('quadrat/qdr-1/a.jpg');

        self::assertNotNull($file);
        self::assertSame(['quadrat/qdr-1/b.jpg'], array_map(static fn (FileEntry $f): string => $f->key, $registry->siblingsOf($file)));
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }

    private static function registry(): FileRegistry
    {
        return new FileRegistry(self::sources());
    }

    /**
     * @return list<FileSourceInterface>
     */
    private static function sources(): array
    {
        return [
            new StubSource('quadrat', 'Quadrats', [
                self::file('quadrat/qdr-1/a.jpg', 'QDR-1', 'quadrat', 4_000_000, '2026-08-21 06:14', '2026-08-21 14:00', 'quadrat/qdr-1/a-t.jpg'),
                self::file('quadrat/qdr-1/b.jpg', 'QDR-1', 'quadrat', 3_000_000, '2026-08-21 06:17', '2026-08-21 14:01', null, ThumbStateEnum::Waiting, 'a swamp at the edge'),
                self::file('quadrat/qdr-2/t.gpx', 'QDR-2', 'quadrat', 1_000_000, '2026-08-19 05:50', '2026-08-19 19:00', null, null, null, 'application/gpx+xml'),
            ]),
            new StubSource('ledger', 'Ledger', [
                self::file('ledger/ldg-9/big.jpg', 'LDG-9', 'ledger', 6_000_000, '2026-08-19 09:02', '2026-08-20 17:44', null, ThumbStateEnum::Failed),
                self::file('ledger/ldg-9/form.pdf', 'LDG-9', 'ledger', 2_000_000, null, '2026-08-12 15:33', null, null, null, 'application/pdf'),
            ]),
        ];
    }

    private static function file(
        string $key,
        string $owner,
        string $module,
        int $bytes = 1_000_000,
        ?string $taken = '2026-08-21 06:00',
        ?string $arrived = '2026-08-21 06:00',
        ?string $thumbKey = null,
        ?ThumbStateEnum $thumbState = null,
        ?string $caption = null,
        string $mime = 'image/jpeg',
    ): FileEntry {
        return new FileEntry(
            $key,
            basename($key),
            $mime,
            $bytes,
            $owner,
            '/records/'.strtolower($owner),
            $module,
            ucfirst($module),
            'quadrat' === $module ? 'west' : 'east',
            'quadrat' === $module ? 'West' : 'East',
            null !== $taken ? new \DateTimeImmutable($taken) : null,
            null !== $arrived ? new \DateTimeImmutable($arrived) : null,
            $thumbKey,
            $thumbState,
            $caption,
        );
    }
}

/**
 * A module, reduced to the seam.
 */
final class StubSource implements FileSourceInterface
{
    /**
     * @param list<FileEntry> $files
     */
    public function __construct(
        private readonly string $slug,
        private readonly string $label,
        private readonly array $files,
    ) {
    }

    public function moduleSlug(): string
    {
        return $this->slug;
    }

    public function moduleLabel(): string
    {
        return $this->label;
    }

    public function attachesTo(): string
    {
        return 'a record’s files';
    }

    public function files(): iterable
    {
        return $this->files;
    }

    public function claimsKey(string $key): bool
    {
        return str_starts_with($key, $this->slug.'/');
    }

    public function guard(string $key, ?UserInterface $user): FileGuard
    {
        return new FileGuard(GuardStateEnum::Allowed, $this->slug.' says so', 'Because it does.');
    }
}

/**
 * A module having a bad day. Every method throws, which is the only interesting
 * thing about it.
 */
final class BrokenSource implements FileSourceInterface
{
    public function moduleSlug(): string
    {
        throw new \RuntimeException('down');
    }

    public function moduleLabel(): string
    {
        throw new \RuntimeException('down');
    }

    public function attachesTo(): string
    {
        throw new \RuntimeException('down');
    }

    public function files(): iterable
    {
        throw new \RuntimeException('down');
    }

    public function claimsKey(string $key): bool
    {
        return str_starts_with($key, 'broken/');
    }

    public function guard(string $key, ?UserInterface $user): FileGuard
    {
        throw new \RuntimeException('down');
    }
}
