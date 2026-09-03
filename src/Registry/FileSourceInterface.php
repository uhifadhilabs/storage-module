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

namespace Uhifadhi\Storage\Registry;

use Symfony\Component\Security\Core\User\UserInterface;
use Uhifadhi\Storage\Model\FileEntry;
use Uhifadhi\Storage\Model\FileGuard;

/**
 * The seam by which an OWNING MODULE puts its files on the hub.
 *
 * The hub is a cross-module registry with ZERO knowledge of observations,
 * incidents or permits — it cannot have any, because knowing what a photograph
 * is attached to is precisely what makes a module a module. So every file on
 * /files was handed over by the module that wrote it, already carrying the one
 * thing that makes it a file on this platform: its owner.
 *
 * Implement this in the module that CALLS EvidenceStorage::store(), and tag the
 * service:
 *
 *     $services->set('patrol.file_source', PatrolFileSource::class)
 *         ->args([service(ObservationPhotoRepository::class), service('router')])
 *         ->tag(FileSourceInterface::TAG);
 *
 * The tag is applied by hand because a reusable bundle is not autoconfigured
 * (symfony.com/doc/current/bundles/best_practices.html). A host's own service
 * carries #[AutoconfigureTag(FileSourceInterface::TAG)] on its own class instead
 * — attributes are read off the definition's class and PHP does not inherit them
 * from an implemented interface.
 *
 * A module that ships no source simply does not appear on the hub. That is the
 * intended reading: the hub grows by MODULES, never by folders.
 */
interface FileSourceInterface
{
    public const string TAG = 'storage.file_source';

    /**
     * The module this source speaks for, e.g. "patrols". One source per module:
     * the hub counts modules by their sources.
     */
    public function moduleSlug(): string;

    /**
     * The same module in the words a warden reads, e.g. "Patrols".
     */
    public function moduleLabel(): string;

    /**
     * Every file this module holds, each already carrying its owner.
     *
     * Returning an iterable rather than an array is deliberate: a module with
     * four thousand photographs should be able to yield them.
     *
     * @return iterable<FileEntry>
     */
    public function files(): iterable;

    /**
     * ONE RECORD'S FILES, asked for by that record's own uuid.
     *
     * The seam another module needs when it is SHOWING a record it does not own —
     * the incidents report flow drawing the photographs of the observation it is
     * filed from, so the filer can see what they are filing about. Walking
     * {@see FileRegistry::all()} and string-matching a uuid inside somebody
     * else's ownerUrl would answer the same question by reading every file in the
     * deployment and guessing at another module's routing; this asks the one
     * module that knows.
     *
     * $source is the token the ASKING module was handed on the wire (the report
     * seam's `source=patrol`), matched against {@see moduleSlug()} by
     * {@see FileRegistry::forRecord()}; a source that is asked about a module
     * that is not its own returns nothing.
     *
     * DEFAULTED TO NOTHING, deliberately: this arrived after the interface
     * shipped, and a module that never addresses its files by record — or has no
     * reason to expose them to another module — is complete without it. PHP
     * interfaces carry no bodies, so that default lives in
     * {@see HoldsNoRecordFilesTrait}: `use` it and the source is done. An empty
     * answer is a fact ("no photographs"), never an error.
     *
     * @return iterable<FileEntry>
     */
    public function filesForRecord(string $source, string $recordUuid): iterable;

    /**
     * Is this key mine? Normally a prefix test on the key the module chose when
     * it called store() — EvidenceKey::rootSegment() reads that prefix back.
     *
     * The same question EvidenceAccessVoterInterface asks, for the same reason:
     * the hub must be able to route one file back to the module that owns it
     * without walking every file in the deployment.
     */
    public function claimsKey(string $key): bool;

    /**
     * What may be done to this file — in THIS module's own words.
     *
     * The hub browses and manages; it never overrules. An incident still in
     * progress will not let go of its evidence and a patrol will not let go of
     * its own track, and only the module knows which of those it is looking at.
     * $user is null for a visitor who is not signed in; deciding that case is
     * the module's job too.
     *
     * Called only where claimsKey() returned true.
     */
    public function guard(string $key, ?UserInterface $user): FileGuard;

    /**
     * One line saying what this module attaches files to, printed in the
     * "Modules holding files" widget: "an observation's photographs · a patrol's
     * own track".
     */
    public function attachesTo(): string;
}
