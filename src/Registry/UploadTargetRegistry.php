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

use Uhifadhi\Storage\Service\EvidenceKey;
use Uhifadhi\Storage\Upload\UploadTargetInterface;

/**
 * WHICH MODULE ANSWERS FOR A TARGET.
 *
 * The same shape as {@see FileRegistry} and for the same reason: this bundle has
 * no idea what an incident is, so the only thing it can do with "incident:0199…"
 * is hand it to whoever claimed the word "incident". The iterator is empty on a
 * host that has installed no module yet, and an empty registry answers for
 * nothing — which is the correct reading of that rather than an error.
 *
 * THREE WAYS TO HAVE NO TARGET, ALL THE SAME ANSWER: a kind nobody claims, a
 * kind two modules claim, and a target string that is only a colon. Attaching a
 * file to a record nobody owns is worse than not attaching it, so ambiguity
 * resolves to refusal the way it does everywhere else in this bundle.
 */
final class UploadTargetRegistry
{
    /**
     * @var array<string, UploadTargetInterface|null>|null null in a slot means
     *                                                     "claimed twice, so nobody"
     */
    private ?array $byKind = null;

    /**
     * @param iterable<UploadTargetInterface> $targets services tagged {@see UploadTargetInterface::TAG}
     */
    public function __construct(
        private readonly iterable $targets,
    ) {
    }

    /**
     * The half of a target string before the first colon. A target with no
     * record behind it — the design's `zone-import` — is that half alone.
     */
    public static function kindOf(string $target): string
    {
        $colon = strpos($target, ':');

        return false === $colon ? $target : substr($target, 0, $colon);
    }

    /**
     * The half after the FIRST colon, kept whole: a record id is a module's
     * business and may well contain another colon.
     */
    public static function idOf(string $target): string
    {
        $colon = strpos($target, ':');

        return false === $colon ? '' : substr($target, $colon + 1);
    }

    public function forTarget(string $target): ?UploadTargetInterface
    {
        return $this->forKind(self::kindOf($target));
    }

    /**
     * The module that WROTE a stored key, read back out of the key's own first
     * segment rather than remembered beside it — one source of truth, so an
     * upload and a removal can never disagree about who owns a file.
     */
    public function forKey(string $key): ?UploadTargetInterface
    {
        return $this->forKind(EvidenceKey::rootSegment($key));
    }

    /**
     * The kinds this installation can receive a file for, in tag order.
     *
     * @return list<string>
     */
    public function kinds(): array
    {
        $kinds = [];
        foreach ($this->targets as $target) {
            $kinds[] = $target->kind();
        }

        return $kinds;
    }

    private function forKind(string $kind): ?UploadTargetInterface
    {
        if ('' === $kind) {
            return null;
        }

        return $this->index()[$kind] ?? null;
    }

    /**
     * @return array<string, UploadTargetInterface|null>
     */
    private function index(): array
    {
        if (null !== $this->byKind) {
            return $this->byKind;
        }

        $index = [];
        foreach ($this->targets as $target) {
            $kind = $target->kind();
            // A second claimant poisons the slot rather than losing to the
            // first: picking a module by tag order would pick one by accident.
            $index[$kind] = \array_key_exists($kind, $index) ? null : $target;
        }

        return $this->byKind = $index;
    }
}
