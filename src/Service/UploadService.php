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

namespace Uhifadhi\Storage\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Storage\Exception\EvidenceRejectedException;
use Uhifadhi\Storage\Exception\EvidenceStorageFailedException;
use Uhifadhi\Storage\Exception\InvalidEvidenceKeyException;
use Uhifadhi\Storage\Exception\UploadRefusedException;
use Uhifadhi\Storage\Model\EvidenceConstraints;
use Uhifadhi\Storage\Model\StoredFile;
use Uhifadhi\Storage\Model\UploadConstraints;
use Uhifadhi\Storage\Registry\UploadTargetRegistry;
use Uhifadhi\Storage\Upload\UploadTargetInterface;

/**
 * THE WHOLE OF WHAT HAPPENS WHEN A FILE ARRIVES.
 *
 * The endpoint is four lines because everything that decides anything is here,
 * and everything this class decides it decides by ASKING THE OWNING MODULE. Its
 * own contribution is the ORDER of the questions, and that order is the security
 * property:
 *
 *   1. WHO — a signed-in person, before any module is asked, so no module has to
 *      remember to.
 *   2. WHICH RECORD — resolved by the target that claims the kind. No record is a
 *      404, and it reads identically to a kind nobody claims, so neither answers
 *      "which records exist?".
 *   3. MAY THEY — the record's own answer.
 *   4. WHAT AND HOW BIG — refused BEFORE a byte is written, so a rejected upload
 *      leaves no trace at all. That is the same promise
 *      {@see EvidenceStorage::store()} makes internally, restated one level up
 *      because the target may take LESS than the deployment does and the
 *      deployment's guard would never see the difference.
 *   5. STORE — under `<kind>/<targetId>/`, which is what lets a removal find its
 *      way back to the same module with nothing but the key.
 *   6. TELL THE MODULE — and if writing its row throws, take the bytes away
 *      again. An orphaned blob is a photograph nobody can see and nobody can
 *      delete.
 *   7. HONOUR THE RECEIPT — "parsed, nothing kept" means the original and its
 *      preview go.
 *
 * ONE CSRF TOKEN FOR THE WHOLE SURFACE, not one per target. A token proves the
 * request came from a page of ours; WHICH page is not a question it can answer
 * and not one that matters here, because every authorization question is the
 * target's and is asked again on every call.
 */
final readonly class UploadService
{
    /**
     * The token id the component renders and both calls check. Spelled once,
     * here, and read by the Twig runtime that mints it and the controller that
     * validates it.
     */
    public const string CSRF_TOKEN_ID = 'storage.upload';

    public function __construct(
        private UploadTargetRegistry $targets,
        private EvidenceStorage $storage,
        private EvidenceConstraints $deployment,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * WHAT THIS TARGET TAKES FROM THIS PERSON — for the component to state up
     * front — or null where it should not be drawn at all.
     *
     * Null covers three cases and they are the same page: a kind no installed
     * module claims, a record that is not there, and a person this record will
     * not take a file from. The endpoint asks all three again on every call, so
     * this is not the guard; it is the component refusing to draw a door it
     * knows will not open. A greyed-out dropzone would tell a reader that a
     * screen exists and they are not trusted with it, which is a worse product
     * than not mentioning it.
     */
    public function constraintsFor(string $target, ?UserInterface $user): ?UploadConstraints
    {
        if (null === $user) {
            return null;
        }

        $module = $this->targets->forTarget($target);
        $record = $module?->accepts(UploadTargetRegistry::idOf($target));

        if (null === $module || null === $record || !$module->mayUpload($record, $user)) {
            return null;
        }

        return $module->constraints($record);
    }

    /**
     * ONE FILE ONTO ONE TARGET.
     *
     * @return array{key: string, label: string, href: string|null, kind: string, bytes: int, thumbnail: string|null}
     *
     * @throws UploadRefusedException with the sentence the component prints
     */
    public function receive(string $target, ?UploadedFile $file, ?UserInterface $user): array
    {
        if (null === $user) {
            throw UploadRefusedException::notPermitted();
        }
        if (null === $file) {
            throw UploadRefusedException::noFile();
        }
        if (!$file->isValid()) {
            throw UploadRefusedException::incomplete();
        }

        $module = $this->targets->forTarget($target);
        if (null === $module) {
            throw UploadRefusedException::unknownTarget();
        }

        $targetId = UploadTargetRegistry::idOf($target);
        $record = $module->accepts($targetId);
        if (null === $record) {
            throw UploadRefusedException::noSuchRecord();
        }

        if (!$module->mayUpload($record, $user)) {
            throw UploadRefusedException::notPermitted();
        }

        $this->guard($module->constraints($record), $file);

        $stored = $this->store($module, $targetId, $file);

        return $this->hand($module, $record, $stored, $user);
    }

    /**
     * TAKE ONE FILE BACK OFF ITS RECORD.
     *
     * Routed by the key's own first segment, so a file can only ever leave
     * through the module that received it.
     *
     * @throws UploadRefusedException
     */
    public function remove(string $key, ?UserInterface $user): void
    {
        if (null === $user) {
            throw UploadRefusedException::mayNotRemove();
        }
        if (!EvidenceKey::isValid($key)) {
            throw UploadRefusedException::noSuchFile();
        }

        $module = $this->targets->forKey($key);
        if (null === $module) {
            throw UploadRefusedException::noSuchFile();
        }

        if (!$module->mayRemove($key, $user)) {
            throw UploadRefusedException::mayNotRemove();
        }

        // THE RECORD FIRST, THE BYTES SECOND — the same order
        // {@see \Uhifadhi\Storage\Removal\FileRemovalInterface} states, and for
        // the same reason: a record still naming a file that is gone is a
        // slightly untidy record, where bytes outliving their record is evidence
        // nobody owns.
        try {
            $module->removed($key, $user);
        } catch (\Throwable $refused) {
            throw UploadRefusedException::removalRefused($refused);
        }

        $this->forget($key);
    }

    /**
     * THE TARGET'S RULES, APPLIED BEFORE ANYTHING IS WRITTEN.
     *
     * Size first, then kind, matching {@see EvidenceConstraints::validate()}'s
     * order — a person who dropped a 40MB photograph should be told about the
     * size rather than about a type nobody objected to.
     *
     * @throws UploadRefusedException
     */
    private function guard(UploadConstraints $constraints, UploadedFile $file): void
    {
        $size = $file->getSize();
        if (false !== $size && !$constraints->fits($size)) {
            throw UploadRefusedException::tooLarge($constraints->maxBytes);
        }

        // The type read from the BYTES, through the deployment's own detector,
        // so the sentence names what was actually sent rather than what the
        // filename claimed.
        $mimeType = $this->deployment->detect($file);
        if (!$constraints->allows($mimeType)) {
            throw UploadRefusedException::kindNotAllowed(self::extensionOf($mimeType));
        }
    }

    /**
     * @throws UploadRefusedException
     */
    private function store(UploadTargetInterface $module, string $targetId, UploadedFile $file): StoredFile
    {
        // THE PREFIX IS THE CONTRACT: `<kind>/<targetId>`. A target with no
        // record behind it has no second segment, and its files sit directly
        // under its kind.
        $prefix = '' === $targetId ? $module->kind() : $module->kind().'/'.$targetId;

        try {
            // A fresh segment per file. Unique within the prefix is all the
            // storage asks for, and a uuid answers it without a round trip.
            return $this->storage->store($file, $prefix, Uuid::v7()->toRfc4122());
        } catch (EvidenceRejectedException $rejected) {
            // The DEPLOYMENT refused what the target allowed — a target that
            // widened past the installation's allowlist, or a type nothing can
            // name. Its own sentence is the honest one.
            throw new UploadRefusedException($rejected->getMessage().' Nothing was written.', previous: $rejected);
        } catch (EvidenceStorageFailedException|InvalidEvidenceKeyException $failed) {
            throw UploadRefusedException::storageFailed($failed);
        }
    }

    /**
     * @return array{key: string, label: string, href: string|null, kind: string, bytes: int, thumbnail: string|null}
     *
     * @throws UploadRefusedException
     */
    private function hand(UploadTargetInterface $module, object $record, StoredFile $stored, UserInterface $user): array
    {
        try {
            $receipt = $module->received($record, $stored, $user);
        } catch (\Throwable $failed) {
            // The bytes are on disk and nothing owns them. Take them away before
            // reporting, or the deployment accumulates files no page can reach
            // and no removal can route to.
            $this->forget($stored->key);

            throw UploadRefusedException::storageFailed($failed);
        }

        $thumbnail = $receipt->keepsBytes && $stored->hasThumbnail()
            ? $this->urls->generate('storage_evidence_show', ['key' => $stored->thumbKey])
            : null;

        if (!$receipt->keepsBytes) {
            $this->forget($stored->key);
        }

        return [
            'key' => $stored->key,
            'label' => $receipt->label,
            'href' => $receipt->href,
            'kind' => $receipt->kind,
            'bytes' => $stored->byteSize,
            'thumbnail' => $thumbnail,
        ];
    }

    /**
     * Delete the original and its preview, never reporting a failure.
     *
     * Every caller here is already on an unhappy path — a rolled-back upload, a
     * parsed-and-discarded import, a completed removal — and turning a cleanup
     * failure into the reported outcome would replace an accurate message with a
     * misleading one.
     */
    private function forget(string $key): void
    {
        try {
            $this->storage->delete($key);
        } catch (\Throwable) {
        }
    }

    /**
     * The extension a refusal names the type by. Null where nothing can name it,
     * and the sentence then says "that kind of file" rather than inventing one.
     */
    private static function extensionOf(?string $mimeType): ?string
    {
        if (null === $mimeType) {
            return null;
        }

        try {
            return EvidenceConstraints::extensionFor($mimeType);
        } catch (EvidenceRejectedException) {
            return null;
        }
    }
}
