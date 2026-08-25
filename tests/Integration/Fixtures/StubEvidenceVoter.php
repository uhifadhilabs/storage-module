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

namespace UhifadhiLabs\Storage\Tests\Integration\Fixtures;

use Symfony\Component\Security\Core\User\UserInterface;
use UhifadhiLabs\Storage\Security\EvidenceAccessVoterInterface;

/**
 * Stands in for an OWNING MODULE (patrol, incidents) in the integration tests.
 * A real one would look up the observation behind the key and ask whether this
 * user's department may see it; this one decides by prefix so the three cases
 * that matter are addressable from a URL:
 *
 *   granted/…  claimed, and allowed
 *   denied/…   claimed, and refused
 *   orphan/…   claimed by NOBODY — the deny-by-default case
 */
final class StubEvidenceVoter implements EvidenceAccessVoterInterface
{
    public function claimsKey(string $key): bool
    {
        return str_starts_with($key, 'granted/') || str_starts_with($key, 'denied/');
    }

    public function mayRead(string $key, ?UserInterface $user): bool
    {
        // Even a claimed key needs somebody to be signed in — a module that
        // forgot this check is the reason the seam passes the user along.
        return null !== $user && str_starts_with($key, 'granted/');
    }
}
