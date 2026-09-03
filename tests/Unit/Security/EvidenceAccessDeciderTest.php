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

namespace Uhifadhi\Storage\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Uhifadhi\Storage\Security\EvidenceAccessDecider;
use Uhifadhi\Storage\Security\EvidenceAccessVoterInterface;

/**
 * The permission seam. This bundle stores bytes; it has no idea what an
 * observation is, who a ranger reports to, or which department may look at a
 * carcass photograph. Only the OWNING module knows, so only the owning module
 * decides — and if no module speaks up for a key, nobody sees it.
 */
final class EvidenceAccessDeciderTest extends TestCase
{
    private function user(): UserInterface
    {
        return new InMemoryUser('ranger@example.test', null);
    }

    /**
     * THE RULE. An unclaimed key is denied. A bundle that answered "sure" for
     * keys no one owns would hand out every future module's evidence the moment
     * that module was installed and before its voter was written.
     */
    public function testAKeyNoVoterClaimsIsDenied(): void
    {
        $decider = new EvidenceAccessDecider([]);

        self::assertFalse($decider->mayRead('observation/x/k.jpg', $this->user()));
    }

    public function testAKeyClaimedByNobodyIsDeniedEvenWhenOtherVotersExist(): void
    {
        $decider = new EvidenceAccessDecider([
            $this->voter(claims: 'incident', grants: true),
        ]);

        self::assertFalse($decider->mayRead('observation/x/k.jpg', $this->user()));
    }

    public function testTheOwningModuleCanGrant(): void
    {
        $decider = new EvidenceAccessDecider([
            $this->voter(claims: 'observation', grants: true),
        ]);

        self::assertTrue($decider->mayRead('observation/x/k.jpg', $this->user()));
    }

    public function testTheOwningModuleCanRefuse(): void
    {
        $decider = new EvidenceAccessDecider([
            $this->voter(claims: 'observation', grants: false),
        ]);

        self::assertFalse($decider->mayRead('observation/x/k.jpg', $this->user()));
    }

    /**
     * Two modules claiming one key is a misconfiguration, and the safe reading
     * of a misconfiguration is the restrictive one: every claimant must agree.
     */
    public function testWhenTwoModulesClaimTheSameKeyARefusalWins(): void
    {
        $decider = new EvidenceAccessDecider([
            $this->voter(claims: 'observation', grants: true),
            $this->voter(claims: 'observation', grants: false),
        ]);

        self::assertFalse($decider->mayRead('observation/x/k.jpg', $this->user()));
    }

    public function testAnAnonymousVisitorIsPassedThroughToTheVoterRatherThanAssumedHarmless(): void
    {
        /** @var \ArrayObject<string, mixed> $seen */
        $seen = new \ArrayObject();
        $decider = new EvidenceAccessDecider([
            $this->recordingVoter('observation', $seen),
        ]);

        $decider->mayRead('observation/x/k.jpg', null);

        self::assertTrue($seen->offsetExists('called'));
        self::assertNull($seen['user']);
    }

    /**
     * The THUMBNAIL is the same evidence at a smaller size, so it is decided by
     * the same voter — a key ending .thumb.jpg must never slip past a prefix
     * match the original would have failed.
     */
    public function testAThumbnailIsDecidedByTheSameVoterAsItsOriginal(): void
    {
        $decider = new EvidenceAccessDecider([
            $this->voter(claims: 'observation', grants: true),
        ]);

        self::assertTrue($decider->mayRead('observation/x/k.jpg.thumb.jpg', $this->user()));
    }

    /** A voter that blows up must not be read as a grant. */
    public function testAVoterThatThrowsIsTreatedAsARefusal(): void
    {
        $exploding = new class implements EvidenceAccessVoterInterface {
            public function claimsKey(string $key): bool
            {
                return true;
            }

            public function mayRead(string $key, ?UserInterface $user): bool
            {
                throw new \RuntimeException('the module is having a bad day');
            }
        };

        self::assertFalse(new EvidenceAccessDecider([$exploding])->mayRead('observation/x/k.jpg', $this->user()));
    }

    private function voter(string $claims, bool $grants): EvidenceAccessVoterInterface
    {
        return new class($claims, $grants) implements EvidenceAccessVoterInterface {
            public function __construct(
                private readonly string $claims,
                private readonly bool $grants,
            ) {
            }

            public function claimsKey(string $key): bool
            {
                return str_starts_with($key, $this->claims.'/');
            }

            public function mayRead(string $key, ?UserInterface $user): bool
            {
                return $this->grants;
            }
        };
    }

    /**
     * @param \ArrayObject<string, mixed> $seen
     */
    private function recordingVoter(string $claims, \ArrayObject $seen): EvidenceAccessVoterInterface
    {
        return new class($claims, $seen) implements EvidenceAccessVoterInterface {
            /** @param \ArrayObject<string, mixed> $seen */
            public function __construct(
                private readonly string $claims,
                private readonly \ArrayObject $seen,
            ) {
            }

            public function claimsKey(string $key): bool
            {
                return str_starts_with($key, $this->claims.'/');
            }

            public function mayRead(string $key, ?UserInterface $user): bool
            {
                $this->seen['called'] = true;
                $this->seen['user'] = $user;

                return false;
            }
        };
    }
}
