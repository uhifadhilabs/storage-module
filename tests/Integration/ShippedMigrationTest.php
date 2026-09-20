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

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Query\Query;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Uhifadhi\Storage\Entity\FileLocation;
use Uhifadhi\Storage\Entity\StorageMove;
use Uhifadhi\Storage\Entity\StorageTarget;
use Uhifadhi\Storage\Migrations\Version20260921000100;

/**
 * THE SHIPPED MIGRATION BUILDS THE SCHEMA THE ENTITIES DESCRIBE.
 *
 * A MODULE THAT OWNS TABLES SHIPS THEIR SCHEMA, and an installation runs
 * `doctrine:migrations:migrate` and never authors SQL for tables it does not
 * own. That promise is only as good as the migration matching the mapping:
 * hand-written DDL that has drifted from an entity is a column that exists in
 * development and not in production, found on the day somebody switches a
 * storage target.
 *
 * FRESH DATABASE → MIGRATE → DIFF IS EMPTY, which is the first of the safety
 * locks the house requires of any package that ships migrations. Here it is
 * run the other way round for speed and with the same meaning: the migration's
 * own SQL is applied to a clean schema and Doctrine is then asked what it
 * would still change. Nothing is the only acceptable answer.
 */
final class ShippedMigrationTest extends KernelTestCase
{
    /** @var list<class-string> */
    private const array OWNED = [StorageTarget::class, FileLocation::class, StorageMove::class];

    public function testTheMigrationLeavesDoctrineWithNothingToChange(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $entityManager = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $connection = $entityManager->getConnection();

        $metadata = self::ownedMetadata($entityManager);
        $tool = new SchemaTool($entityManager);

        // A CLEAN SLATE FOR THE TABLES THIS BUNDLE OWNS, and only those: the
        // rest of the schema belongs to the core and is not this test's to
        // drop.
        $tool->dropSchema($metadata);

        foreach (self::migrationSql($connection) as $sql) {
            $connection->executeStatement($sql);
        }

        $pending = $tool->getUpdateSchemaSql($metadata);
        $pending = array_values(array_filter(
            $pending,
            static fn (string $sql): bool => self::touchesOwnedTable($sql),
        ));

        self::assertSame([], $pending, \sprintf(
            "The shipped migration and the entity mapping disagree. Doctrine would still run:\n%s",
            implode("\n", $pending),
        ));

        // Put the suite's own schema back: every other test in this kernel
        // expects the tables to be there.
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    /**
     * THE MIGRATION'S OWN STATEMENTS, taken from the class rather than
     * retyped — a copy of the DDL in a test proves only that the copy is
     * consistent with itself.
     *
     * @return list<string>
     */
    private static function migrationSql(Connection $connection): array
    {
        $migration = new Version20260921000100($connection, new \Psr\Log\NullLogger());
        $migration->up($connection->createSchemaManager()->introspectSchema());

        $reflection = new \ReflectionProperty(AbstractMigration::class, 'plannedSql');
        /** @var list<Query> $planned */
        $planned = $reflection->getValue($migration);

        return array_map(static fn (Query $query): string => $query->getStatement(), $planned);
    }

    /**
     * @return list<ClassMetadata<object>>
     */
    private static function ownedMetadata(EntityManagerInterface $entityManager): array
    {
        return array_map(
            static fn (string $class): ClassMetadata => $entityManager->getClassMetadata($class),
            self::OWNED,
        );
    }

    /**
     * Doctrine's update SQL covers the whole connection; only what it would
     * do to THIS bundle's tables is this test's business.
     */
    private static function touchesOwnedTable(string $sql): bool
    {
        foreach (['storage_target', 'storage_file_location', 'storage_move'] as $table) {
            if (str_contains($sql, $table)) {
                return true;
            }
        }

        return false;
    }
}
