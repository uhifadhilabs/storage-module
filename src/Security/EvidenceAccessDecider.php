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

namespace Uhifadhi\Storage\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Collects the installed modules' voters and reaches one answer.
 *
 * DENY BY DEFAULT, in the strong sense: a grant requires that at least one
 * module claimed the key AND that every module which claimed it agreed. Silence
 * is a refusal. Two claimants disagreeing is a refusal. A voter that throws is a
 * refusal.
 *
 * This is not defensive habit. Evidence is a snare, a carcass, sometimes a
 * person; the cost of wrongly showing one is not symmetric with the cost of
 * wrongly hiding one, so every ambiguous case resolves the same way.
 */
final class EvidenceAccessDecider
{
    /**
     * @param iterable<EvidenceAccessVoterInterface> $voters services tagged "uhifadhi.evidence_access_voter"
     */
    public function __construct(
        private readonly iterable $voters,
    ) {
    }

    public function mayRead(string $key, ?UserInterface $user): bool
    {
        $claimed = false;

        foreach ($this->voters as $voter) {
            try {
                if (!$voter->claimsKey($key)) {
                    continue;
                }

                $claimed = true;

                if (!$voter->mayRead($key, $user)) {
                    return false;
                }
            } catch (\Throwable) {
                // A module having a bad day must not be readable as consent.
                // Swallowed rather than propagated so one broken voter cannot
                // take out access to every OTHER module's evidence as well.
                return false;
            }
        }

        return $claimed;
    }
}
