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

namespace Uhifadhi\Storage\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Uhifadhi\Storage\Service\ServerUploadLimitService;
use Uhifadhi\Storage\Tests\Unit\Service\Fixtures\RecordingLogger;

/**
 * THE MISCONFIGURATION THAT ANNOUNCES ITSELF.
 *
 * A deployment whose php.ini accepts less than this module was configured to
 * accept refuses evidence nobody chose to refuse, and the refusal looks like a
 * broken upload. The log line is how the person who can fix it finds out without
 * a ranger telling them first.
 */
#[CoversClass(ServerUploadLimitService::class)]
final class ServerUploadLimitServiceTest extends TestCase
{
    private const int TWO_MEBIBYTES = 2 * 1024 * 1024;

    private const int TWELVE_MEBIBYTES = 12 * 1024 * 1024;

    public function testAServerCeilingBelowTheConfiguredCapIsReportedWithBothNumbersAndTheIniKeys(): void
    {
        $logger = new RecordingLogger();

        new ServerUploadLimitService($logger, self::TWELVE_MEBIBYTES, self::TWO_MEBIBYTES)
            ->warnIfNarrowerThanConfigured();

        $warnings = $logger->messagesAt('warning');
        self::assertCount(1, $warnings);
        self::assertStringContainsString('PHP accepts 2.1 MB per upload', $warnings[0]);
        self::assertStringContainsString('storage.evidence.max_bytes is 12.6 MB', $warnings[0]);
        self::assertStringContainsString('upload_max_filesize', $warnings[0]);
        self::assertStringContainsString('post_max_size', $warnings[0]);
    }

    /**
     * ONCE PER PROCESS. A busy installation would otherwise write the same
     * sentence on every upload and bury the ones that are about a file.
     */
    public function testItIsSaidOncePerProcessRatherThanOnEveryUpload(): void
    {
        $logger = new RecordingLogger();
        $limit = new ServerUploadLimitService($logger, self::TWELVE_MEBIBYTES, self::TWO_MEBIBYTES);

        $limit->warnIfNarrowerThanConfigured();
        $limit->warnIfNarrowerThanConfigured();
        $limit->warnIfNarrowerThanConfigured();

        self::assertCount(1, $logger->messagesAt('warning'));
    }

    public function testAServerThatAcceptsWhatWasConfiguredSaysNothing(): void
    {
        $logger = new RecordingLogger();

        new ServerUploadLimitService($logger, self::TWELVE_MEBIBYTES, self::TWELVE_MEBIBYTES)
            ->warnIfNarrowerThanConfigured();
        new ServerUploadLimitService($logger, self::TWO_MEBIBYTES, self::TWELVE_MEBIBYTES)
            ->warnIfNarrowerThanConfigured();

        self::assertSame([], $logger->records);
    }

    /** A host with no logger at all must not turn a warning into a crash. */
    public function testAnInstallationWithoutALoggerIsNotBrokenByTheCheck(): void
    {
        new ServerUploadLimitService(null, self::TWELVE_MEBIBYTES, self::TWO_MEBIBYTES)
            ->warnIfNarrowerThanConfigured();

        $this->expectNotToPerformAssertions();
    }

    public function testTheCeilingIsWhatPhpIsRunningWithUnlessOneIsGiven(): void
    {
        self::assertSame(
            (int) min(UploadedFile::getMaxFilesize(), \PHP_INT_MAX),
            new ServerUploadLimitService(null, self::TWELVE_MEBIBYTES)->maxBytes(),
        );
        self::assertSame(
            self::TWO_MEBIBYTES,
            new ServerUploadLimitService(null, self::TWELVE_MEBIBYTES, self::TWO_MEBIBYTES)->maxBytes(),
        );
    }
}
