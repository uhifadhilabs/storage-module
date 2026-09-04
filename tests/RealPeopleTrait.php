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

namespace Uhifadhi\Storage\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Uhifadhi\Team\Entity\User;
use Uhifadhi\Team\Enum\TeamRoleEnum;

/**
 * THE PEOPLE THE SUITE SIGNS IN, AND THE SCHEMA THEY LIVE IN.
 *
 * They are REAL ACCOUNTS, not InMemoryUser, and that changed when this module
 * joined the fleet. The Files hub is a widget dashboard; a dashboard layout
 * belongs to a PERSON; the row storing one carries a NOT NULL foreign key to
 * that person's own table. An in-memory user has no row to point at, so the
 * moment the hub started riding the real widget framework instead of a double,
 * it started needing accounts that exist. The class is
 * uhifadhi/team-module's, which is what an installation has.
 *
 * A PASSWORD THAT DOES NOT CHANGE BETWEEN REQUESTS. Symfony's ContextListener
 * refreshes the token on every request and refuses if the user has changed
 * ("Cannot refresh token because user has changed"), which drops the session
 * silently and makes every later assertion pass for the wrong reason. A row
 * fetched from the database is byte-identical to the one signed in, so this is
 * one thing the real account gets right for free that the in-memory pair had to
 * be careful about.
 *
 * THE SCHEMA IS BUILT, NOT MIGRATED. These are somebody else's tables — this
 * bundle maps none of them — so what the suite needs is a database shaped like
 * the installation's, not a history of how it got there.
 */
trait RealPeopleTrait
{
    protected static function buildSchema(): void
    {
        $entityManager = self::entityManager();
        $tool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    /** Anyone signed in. The hub is open to them and shows what their permissions allow. */
    protected static function rangerAccount(): User
    {
        return self::person('ranger@example.test', 'Amina', 'Kileo', TeamRoleEnum::Staff);
    }

    /**
     * Somebody carrying the deployment's administrator permission, which is the
     * only thing "Where files go" asks for. The Admin tier is what emits
     * ROLE_ADMIN.
     */
    protected static function wardenAccount(): User
    {
        return self::person('warden@example.test', 'Joseph', 'Mwakalinga', TeamRoleEnum::Admin);
    }

    /**
     * Created once and found thereafter: two tests in one class sign the same
     * person in, and a second INSERT of the same email is a unique-index
     * violation rather than a second person.
     */
    private static function person(string $email, string $first, string $last, TeamRoleEnum $tier): User
    {
        $entityManager = self::entityManager();
        $existing = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing instanceof User) {
            return $existing;
        }

        $person = new User()
            ->setEmail($email)
            ->setFirstName($first)
            ->setLastName($last)
            ->setTeamRole($tier)
            // Never verified against: nothing in this suite signs in through a
            // form. It is here because the column is NOT NULL.
            ->setPassword('not-used-here')
            ->setVerified(true);

        $entityManager->persist($person);
        $entityManager->flush();

        return $person;
    }

    private static function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        \assert($entityManager instanceof EntityManagerInterface);

        return $entityManager;
    }
}
