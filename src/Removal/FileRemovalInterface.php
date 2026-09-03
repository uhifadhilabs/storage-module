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

namespace Uhifadhi\Storage\Removal;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * REMOVE, NEVER DELETE — and the record keeps a line saying it happened.
 *
 * The hub's whole promise about removal is that it is a recorded event on the
 * OWNING RECORD, not a disappearance. This bundle cannot keep that promise on
 * its own: it has no records to write a trail line onto. So removal is a hook,
 * implemented by the same module that implements FileSourceInterface, and the
 * hub offers the control only where BOTH the guard allows it AND the module
 * ships this interface. A module that has not written its trail line yet simply
 * does not offer removal — which is the safe way round.
 *
 *     final class PatrolFileSource implements FileSourceInterface, FileRemovalInterface
 *
 * The wording everywhere is REMOVE. Not "delete": what leaves is the file, and
 * what stays is the observation, one line longer than it was.
 */
interface FileRemovalInterface
{
    /**
     * Remove one file, and write the removal onto its owning record's trail.
     *
     * The module is responsible for BOTH halves, in this order: the trail line
     * first, the bytes second. A trail line without bytes is a slightly untidy
     * record; bytes without a trail line is the promise broken.
     *
     * @param string      $key    the file's storage key
     * @param string|null $reason the reason the person gave, where the guard asked for one
     *
     * @throws \RuntimeException where the record refuses after all — the guard is
     *                           a statement about a moment, and the moment can pass
     *                           between drawing the page and pressing the button
     */
    public function remove(string $key, ?UserInterface $user, ?string $reason): void;
}
