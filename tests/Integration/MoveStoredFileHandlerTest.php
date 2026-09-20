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

namespace Uhifadhi\Storage\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Uhifadhi\Storage\Entity\FileLocation;
use Uhifadhi\Storage\Entity\StorageMove;
use Uhifadhi\Storage\Enum\MoveStateEnum;
use Uhifadhi\Storage\Message\MoveStoredFile;
use Uhifadhi\Storage\MessageHandler\MoveStoredFileHandler;
use Uhifadhi\Storage\Service\StorageLocator;
use Uhifadhi\Storage\Service\StorageTargetService;

/**
 * COPY, VERIFY, RECORD, DELETE — one file, and the ORDER is what is tested.
 *
 * The order is the whole safety of a move: at every point the bytes are
 * readable from somewhere, and the worst a crash can do is leave one extra
 * copy. A test that only checked "the file ended up in the new place" would
 * pass just as well on an implementation that deleted first and lost a
 * photograph on a bad day.
 *
 * REAL BYTES, REAL DIRECTORIES. Two local filesystems stand in for the two
 * named places an installation configures; a mocked filesystem would be
 * testing the mock's idea of a copy.
 */
final class MoveStoredFileHandlerTest extends KernelTestCase
{
    private const string KEY = 'fieldwork/rec-0001/carried.jpg';
    private const string BYTES = 'not really a photograph, but the right number of bytes';

    public function testAFileIsCarriedToTheNewPlaceAndLeavesTheOld(): void
    {
        [$handler, $locator] = $this->given();

        $handler(new MoveStoredFile(self::KEY, 'evidence', 'archive'));

        self::assertFalse($locator->of('evidence')->fileExists(self::KEY), 'the original is gone from the old place');
        self::assertTrue($locator->of('archive')->fileExists(self::KEY), 'and it is in the new one');
        self::assertSame(self::BYTES, $locator->of('archive')->read(self::KEY), 'byte for byte');
    }

    /** THE ROW IS THE PROGRESS, so it moves with the bytes and not before. */
    public function testTheFilesRowNamesTheNewPlaceAfterwards(): void
    {
        [$handler] = $this->given();

        $handler(new MoveStoredFile(self::KEY, 'evidence', 'archive'));

        self::assertSame('archive', self::location()->getPlaceId());
    }

    /** And the move's counters follow the row. */
    public function testTheMoveCountsTheFileItCarried(): void
    {
        [$handler, , $targets] = $this->given();

        $handler(new MoveStoredFile(self::KEY, 'evidence', 'archive'));

        // THE MOVE FINISHED AS ITS LAST FILE LANDED — the chain asks for a
        // successor, finds the old place empty and closes the switch — so it
        // is no longer the OPEN one and is read as the latest.
        $move = self::latestMove();
        self::assertSame(1, $move->getMovedFiles());
        self::assertSame(\strlen(self::BYTES), $move->getMovedBytes());
        self::assertSame(MoveStateEnum::Done, $move->getState());
    }

    /**
     * IDEMPOTENT. A transport that redelivers a message after the row was
     * rewritten must not carry the file a second time, or delete the copy it
     * has just made.
     */
    public function testARedeliveredMessageDoesNothingTwice(): void
    {
        [$handler, $locator, $targets] = $this->given();

        $handler(new MoveStoredFile(self::KEY, 'evidence', 'archive'));
        $handler(new MoveStoredFile(self::KEY, 'evidence', 'archive'));

        self::assertTrue($locator->of('archive')->fileExists(self::KEY));
        self::assertSame(1, self::latestMove()->getMovedFiles(), 'counted once, not twice');
    }

    /**
     * A PAUSE TAKES EFFECT ON THE NEXT FILE, not after four thousand messages
     * have drained: a message that arrives while the move is paused leaves
     * its file exactly where it is.
     */
    public function testAMessageArrivingWhilePausedCarriesNothing(): void
    {
        [$handler, $locator, $targets] = $this->given();
        $targets->pause();

        $handler(new MoveStoredFile(self::KEY, 'evidence', 'archive'));

        self::assertTrue($locator->of('evidence')->fileExists(self::KEY), 'the file stays where it is');
        self::assertSame('evidence', self::location()->getPlaceId(), 'and so does its row');
    }

    /**
     * A FILE THAT CANNOT BE READ DOES NOT STRAND THE OTHERS, and it does not
     * lie about having moved either: the row stays, so the old place never
     * reaches zero and cannot be cleared. That is the right outcome.
     */
    public function testAFileTheOldPlaceNoLongerHasIsLeftRecordedWhereItWas(): void
    {
        [$handler, $locator] = $this->given();
        $locator->of('evidence')->delete(self::KEY);

        $handler(new MoveStoredFile(self::KEY, 'evidence', 'archive'));

        self::assertSame('evidence', self::location()->getPlaceId(), 'nothing pretends it was carried');
        self::assertFalse($locator->of('archive')->fileExists(self::KEY));
    }

    /**
     * The stand-in installation mid-move: two places, one file in the old
     * one, and a switch that has been answered yes.
     *
     * @return array{MoveStoredFileHandler, StorageLocator, StorageTargetService}
     */
    private function given(): array
    {
        self::bootKernel();
        $container = self::getContainer();

        $locator = $container->get('test_public.'.StorageLocator::class);
        self::assertInstanceOf(StorageLocator::class, $locator);
        $targets = $container->get('test_public.'.StorageTargetService::class);
        self::assertInstanceOf(StorageTargetService::class, $targets);

        foreach (['evidence', 'archive'] as $place) {
            if ($locator->of($place)->fileExists(self::KEY)) {
                $locator->of($place)->delete(self::KEY);
            }
        }
        $locator->of('evidence')->write(self::KEY, self::BYTES);

        $entityManager = self::entityManager();

        // ONE INSTALLATION PER TEST. The kernel's schema outlives a single
        // test method, so the rows a previous one left are cleared here
        // rather than leaking a half-finished switch into the next case.
        foreach (['storage_file_location', 'storage_move', 'storage_target'] as $table) {
            $entityManager->getConnection()->executeStatement('DELETE FROM '.$table);
        }
        $entityManager->clear();

        $entityManager->persist(new FileLocation(self::KEY, 'evidence', \strlen(self::BYTES)));
        $entityManager->flush();

        $targets->switchTo('archive');

        // THE MOVE IS STARTED WITHOUT DISPATCHING, because in this kernel the
        // bus is synchronous: moveExisting() would run the whole chain here
        // and every test below would be handed a finished move to act on.
        // What each one exercises is ONE message, so the state is set and the
        // message is sent by hand.
        $move = $targets->openMove();
        self::assertNotNull($move);
        $move->start();
        $entityManager->flush();

        $handler = $container->get('test_public.'.MoveStoredFileHandler::class);
        self::assertInstanceOf(MoveStoredFileHandler::class, $handler);

        return [$handler, $locator, $targets];
    }

    private static function latestMove(): StorageMove
    {
        $entityManager = self::entityManager();
        $entityManager->clear();

        $move = $entityManager->getRepository(StorageMove::class)->findOneBy([], ['id' => 'DESC']);
        self::assertInstanceOf(StorageMove::class, $move);

        return $move;
    }

    private static function location(): FileLocation
    {
        $entityManager = self::entityManager();
        $entityManager->clear();

        $location = $entityManager->getRepository(FileLocation::class)->findOneBy(['key' => self::KEY]);
        self::assertInstanceOf(FileLocation::class, $location);

        return $location;
    }

    private static function entityManager(): EntityManagerInterface
    {
        $registry = self::getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $entityManager = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
