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

namespace Uhifadhi\Storage\Upload;

use Symfony\Component\Security\Core\User\UserInterface;
use Uhifadhi\Storage\Model\StoredFile;
use Uhifadhi\Storage\Model\UploadConstraints;
use Uhifadhi\Storage\Model\UploadReceipt;

/**
 * THE ONE THING A MODULE WRITES TO GAIN UPLOADS.
 *
 * Storage owns the whole upload feature: the component, the endpoint, the
 * progress, the refusal sentences, the removal question. A module that wants a
 * file to be attachable somewhere implements this interface, tags it, and puts
 * ONE line in a template:
 *
 *     {{ render_upload('incident:' ~ incident.uuid, 'tile') }}
 *
 * No controller, no route, no JavaScript, no stylesheet. That is the whole of
 * the module's side, and it is deliberate: a file entered the product through
 * five doors that each drew their own box, their own error and their own
 * missing progress bar, and the fifth one had no way to take a file back off.
 *
 * WHAT THE MODULE ANSWERS, and why the storage cannot:
 *
 *   - WHICH RECORD. Storage has never heard of an incident. It is handed
 *     "incident:0199…" and hands the half after the colon to whoever claims the
 *     half before it.
 *   - WHO MAY. A permission is a fact about a record, and records are modules'.
 *   - WHAT AND HOW BIG. The deployment's allowlist is the default; a target that
 *     takes less says so.
 *   - WHAT IT BECAME. Evidence on a case file, a photograph with a position, a
 *     parsed track, a boundary. Only the module knows, and the chip on the
 *     finished row prints the module's word for it.
 *
 * THE KIND IS ALSO THE KEY PREFIX, and that is load-bearing rather than
 * convenient. Every file this target receives is stored under `<kind>/<targetId>/…`,
 * so {@see \Uhifadhi\Storage\Service\EvidenceKey::rootSegment()} reads the owning
 * module back OUT of a stored key — which is how a removal routes to the right
 * module without the storage keeping a table of its own, and it is the same
 * prefix {@see \Uhifadhi\Storage\Security\EvidenceAccessVoterInterface} and
 * {@see \Uhifadhi\Storage\Registry\FileSourceInterface} already claim on. A
 * module writes its prefix once, here, and its voter and its file source read it
 * back rather than remembering it.
 *
 * TAG IT BY HAND. A reusable bundle is not autoconfigured
 * (https://symfony.com/doc/current/bundles/best_practices.html), so:
 *
 *     $services->set('incident.upload_target', IncidentEvidenceTarget::class)
 *         ->args([service(IncidentRepository::class), service('incident.evidence_service')])
 *         ->tag(UploadTargetInterface::TAG);
 *
 * A host's own service carries #[AutoconfigureTag(UploadTargetInterface::TAG)]
 * on its own class instead — attributes are read off the definition's class and
 * PHP does not inherit them from an implemented interface.
 *
 * A KIND NOBODY CLAIMS IS REFUSED, and so is a kind two modules claim. Both are
 * "no target", for the same reason the permission contract denies an unclaimed
 * key: attaching a file to a record nobody owns is worse than not attaching it.
 */
interface UploadTargetInterface
{
    public const string TAG = 'storage.upload_target';

    /**
     * THE TARGET KEY PREFIX — `incident`, `observation`, `patrol-track`,
     * `zone-boundary`.
     *
     * It is the half of a target string before the colon, AND the first segment
     * of every key this target's files are stored under. One plain segment:
     * letters, digits, dot, underscore, hyphen ({@see \Uhifadhi\Storage\Service\EvidenceKey}).
     */
    public function kind(): string;

    /**
     * THE RECORD THIS FILE IS FOR, or null when there is none.
     *
     * $targetId is the half of the target string after the colon — usually a
     * uuid, and always attacker-controlled text. It is EMPTY for a target that
     * has no record behind it (a boundary import belongs to the import, not to a
     * row); such a target returns whatever object it wants to be handed back and
     * never null.
     *
     * Null means "no such record", and the component draws the storage's
     * target-unknown refusal. It must never mean "you may not" — that is the
     * next question, and answering it here would turn 403 into 404 for anyone
     * probing which records exist.
     */
    public function accepts(string $targetId): ?object;

    /**
     * MAY THIS PERSON ATTACH A FILE HERE?
     *
     * $record is what {@see accepts()} returned. $user is never null: the
     * endpoint refuses an anonymous upload before any target is asked, because
     * no module should have to remember to.
     */
    public function mayUpload(object $record, UserInterface $user): bool;

    /**
     * WHAT THIS RECORD TAKES.
     *
     * {@see UploadConstraints::from()} with the deployment's own
     * {@see \Uhifadhi\Storage\Model\EvidenceConstraints} is the right answer for
     * almost every target, and inherits the installation's configuration for
     * free. Narrow it where the record genuinely takes less; widening past the
     * deployment's allowlist promises what the storage will then refuse.
     */
    public function constraints(object $record): UploadConstraints;

    /**
     * THE BYTES ARE STORED. WRITE THE ROW.
     *
     * Called once, after the file passed the guard and landed in the private
     * storage under this target's prefix. The module persists whatever it keeps
     * about the file and returns what the file BECAME.
     *
     * A receipt that says {@see UploadReceipt::parsed()} tells the storage the
     * blob is no longer wanted; it and its preview are deleted after this
     * returns. Everything a module needs from the bytes must therefore be taken
     * before the receipt comes back.
     *
     * Throwing here fails the upload and the stored blob is removed, so a module
     * that cannot write its row never leaves an orphan behind.
     */
    public function received(object $record, StoredFile $file, UserInterface $user): UploadReceipt;

    /**
     * MAY THIS PERSON TAKE THIS FILE BACK OFF?
     *
     * Addressed BY KEY rather than by record, because that is what a removal
     * has: the component holds the key the upload returned and nothing else, and
     * the key's first segment is what routed the call to this module. A key this
     * module does not actually hold answers false — the same reading the hub's
     * guard gives an unclaimed key.
     *
     * A different question from {@see mayUpload()} on purpose: a verified
     * incident may well let a ranger add evidence and refuse to let anybody
     * remove it.
     */
    public function mayRemove(string $key, UserInterface $user): bool;

    /**
     * THE FILE IS GOING. UNPICK THE RECORD.
     *
     * Called BEFORE the bytes are deleted, so a module that refuses after all
     * can throw and leave the file where it is. The module drops its own row and
     * writes whatever line its record keeps about the removal; the storage
     * deletes the blob and its preview once this returns.
     */
    public function removed(string $key, UserInterface $user): void;
}
