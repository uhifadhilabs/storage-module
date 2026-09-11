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

namespace Uhifadhi\Storage\Tests\Integration\Fixtures;

use Symfony\Component\Security\Core\User\UserInterface;
use Uhifadhi\Storage\Model\EvidenceConstraints;
use Uhifadhi\Storage\Model\StoredFile;
use Uhifadhi\Storage\Model\UploadConstraints;
use Uhifadhi\Storage\Model\UploadReceipt;
use Uhifadhi\Storage\Service\EvidenceKey;
use Uhifadhi\Storage\Upload\UploadTargetInterface;

/**
 * THE OWNING MODULE, PLAYED BY A FIXTURE.
 *
 * The endpoint's whole behaviour is "ask the module", so a suite that had no
 * module would be asserting the questions and never the answers. This one stands
 * in for an installation's real target and is deliberately the only thing in the
 * suite that knows what a record is — exactly the split the contract draws.
 *
 * THE RECORD ID CHOOSES THE ANSWER, so one fixture covers every branch the
 * endpoint has: `open` takes anything the deployment does, `locked` refuses the
 * upload, `sealed` takes files and refuses to give one back, `tiny` and `png`
 * narrow the rules, `parsed` keeps nothing, and anything else does not exist.
 * A target string carries that id, and so does the first sub-segment of every
 * key stored under it, which is how a REMOVAL — which has only a key — reaches
 * the same answer.
 */
final class StubUploadTarget implements UploadTargetInterface
{
    public const string KIND = 'stub';

    /** Records that exist. Anything else resolves to null and the endpoint says so. */
    public const array RECORDS = ['open', 'locked', 'sealed', 'tiny', 'png', 'parsed'];

    /** @var list<string> the keys this target was told about, newest last */
    public array $received = [];

    /** @var list<string> the keys unpicked from their record */
    public array $unpicked = [];

    public function kind(): string
    {
        return self::KIND;
    }

    public function accepts(string $targetId): ?object
    {
        return \in_array($targetId, self::RECORDS, true) ? new StubUploadRecord($targetId) : null;
    }

    public function mayUpload(object $record, UserInterface $user): bool
    {
        return $record instanceof StubUploadRecord && 'locked' !== $record->id;
    }

    public function constraints(object $record): UploadConstraints
    {
        $id = $record instanceof StubUploadRecord ? $record->id : '';

        return match ($id) {
            // Smaller than every fixture image, so the size refusal is reached
            // without a suite that has to ship a 13MB file to provoke it.
            'tiny' => new UploadConstraints(EvidenceConstraints::DEFAULT_MIME_TYPES, 64),
            'png' => new UploadConstraints(['image/png'], EvidenceConstraints::DEFAULT_MAX_BYTES),
            default => UploadConstraints::from(EvidenceConstraints::default()),
        };
    }

    public function received(object $record, StoredFile $file, UserInterface $user): UploadReceipt
    {
        $this->received[] = $file->key;

        if ($record instanceof StubUploadRecord && 'parsed' === $record->id) {
            return UploadReceipt::parsed($file->clientName ?? $file->key, '/stub/parsed');
        }

        return UploadReceipt::stored($file->clientName ?? $file->key, '/stub/'.$file->key);
    }

    public function mayRemove(string $key, UserInterface $user): bool
    {
        return 'sealed' !== self::recordIdIn($key);
    }

    public function removed(string $key, UserInterface $user): void
    {
        $this->unpicked[] = $key;
    }

    /**
     * The record id out of a stored key — `stub/open/0199….jpg`. A removal
     * carries only the key, so the fixture reads its record back out of it the
     * same way a real module's repository looks its row up by path.
     */
    private static function recordIdIn(string $key): string
    {
        $segments = explode('/', EvidenceKey::original($key));

        return $segments[1] ?? '';
    }
}
