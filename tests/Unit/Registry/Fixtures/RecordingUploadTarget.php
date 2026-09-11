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

namespace Uhifadhi\Storage\Tests\Unit\Registry\Fixtures;

use Symfony\Component\Security\Core\User\UserInterface;
use Uhifadhi\Storage\Model\EvidenceConstraints;
use Uhifadhi\Storage\Model\StoredFile;
use Uhifadhi\Storage\Model\UploadConstraints;
use Uhifadhi\Storage\Model\UploadReceipt;
use Uhifadhi\Storage\Upload\UploadTargetInterface;

/**
 * A target that answers for one kind and nothing else, for the registry's own
 * unit test. It resolves nothing and stores nothing: the registry's whole job is
 * choosing WHICH target answers, and a fixture that also had opinions about
 * records would be testing two things at once.
 */
final class RecordingUploadTarget implements UploadTargetInterface
{
    public function __construct(private readonly string $kind)
    {
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function accepts(string $targetId): ?object
    {
        return null;
    }

    public function mayUpload(object $record, UserInterface $user): bool
    {
        return false;
    }

    public function constraints(object $record): UploadConstraints
    {
        return UploadConstraints::from(EvidenceConstraints::default());
    }

    public function received(object $record, StoredFile $file, UserInterface $user): UploadReceipt
    {
        return UploadReceipt::stored($file->key);
    }

    public function mayRemove(string $key, UserInterface $user): bool
    {
        return false;
    }

    public function removed(string $key, UserInterface $user): void
    {
    }
}
