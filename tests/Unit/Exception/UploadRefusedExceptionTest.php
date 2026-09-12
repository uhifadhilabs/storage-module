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

namespace Uhifadhi\Storage\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Storage\Exception\UploadRefusedException;
use Uhifadhi\Storage\Model\Bytes;

/**
 * WHICH SENTENCE A FAILED UPLOAD GETS.
 *
 * PHP reports every failure through one integer, and reading them all as "that
 * upload did not arrive intact" told a ranger their phone or the network had
 * broken when the truth was that the server was configured to accept 2 MB. The
 * distinction here is the one Symfony's own UploadedFile::getErrorMessage()
 * draws: a size code names a LIMIT, everything else is a transfer that failed.
 */
#[CoversClass(UploadRefusedException::class)]
final class UploadRefusedExceptionTest extends TestCase
{
    private const string PHOTO = __DIR__.'/../../Fixtures/images/tiny-100x80.jpg';

    private const int TWO_MEBIBYTES = 2 * 1024 * 1024;

    /**
     * THE NUMBER IN THE SENTENCE IS THE SERVER'S, NOT THE MODULE'S. A person who
     * has just been refused a 4 MB photograph cannot act on "12 MB is allowed",
     * which is what the configured cap would have told them.
     */
    public function testAnUploadPhpItselfCutShortNamesTheServersOwnLimit(): void
    {
        $refusal = UploadRefusedException::forFailedUpload(self::failing(\UPLOAD_ERR_INI_SIZE), self::TWO_MEBIBYTES);

        self::assertSame('That file is larger than this server accepts (2.1 MB). Nothing was written.', $refusal->getMessage());
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $refusal->statusCode);
    }

    /**
     * The form's own MAX_FILE_SIZE, which is the same fact to whoever is holding
     * the file — Symfony's getErrorMessage() words the two alike too.
     */
    public function testAFormDeclaredSizeCapReadsTheSameWay(): void
    {
        $refusal = UploadRefusedException::forFailedUpload(self::failing(\UPLOAD_ERR_FORM_SIZE), self::TWO_MEBIBYTES);

        self::assertSame('That file is larger than this server accepts (2.1 MB). Nothing was written.', $refusal->getMessage());
    }

    public function testATruncatedUploadStillSaysItDidNotArriveIntact(): void
    {
        $refusal = UploadRefusedException::forFailedUpload(self::failing(\UPLOAD_ERR_PARTIAL), self::TWO_MEBIBYTES);

        self::assertSame('That upload did not arrive intact. Nothing was written.', $refusal->getMessage());
    }

    public function testNoFileAtAllSaysSo(): void
    {
        $refusal = UploadRefusedException::forFailedUpload(self::failing(\UPLOAD_ERR_NO_FILE), self::TWO_MEBIBYTES);

        self::assertSame('No file arrived with that upload. Nothing was written.', $refusal->getMessage());
    }

    /**
     * A missing temp directory, a write that failed, an extension that stopped
     * it: none of them is a limit anybody can raise from the sentence, and all
     * of them are a transfer that did not complete.
     */
    public function testEveryOtherFailureKeepsTheIntactWording(): void
    {
        foreach ([\UPLOAD_ERR_NO_TMP_DIR, \UPLOAD_ERR_CANT_WRITE, \UPLOAD_ERR_EXTENSION] as $code) {
            self::assertSame(
                'That upload did not arrive intact. Nothing was written.',
                UploadRefusedException::forFailedUpload(self::failing($code), self::TWO_MEBIBYTES)->getMessage(),
            );
        }
    }

    /** With no limit passed, the one PHP is actually running with. */
    public function testTheLimitDefaultsToWhatPhpItselfAccepts(): void
    {
        $refusal = UploadRefusedException::forFailedUpload(self::failing(\UPLOAD_ERR_INI_SIZE));

        self::assertStringContainsString(
            '('.Bytes::human((int) min(UploadedFile::getMaxFilesize(), \PHP_INT_MAX)).')',
            $refusal->getMessage(),
        );
    }

    private static function failing(int $error): UploadedFile
    {
        return new UploadedFile(self::PHOTO, 'IMG_1204.jpg', 'image/jpeg', $error, test: true);
    }
}
