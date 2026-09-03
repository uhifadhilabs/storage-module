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

namespace Uhifadhi\Storage\Model;

use Uhifadhi\Storage\Enum\FileKindEnum;

/**
 * The filter row, as a value.
 *
 * Every chip on the hub is ONE query parameter and nothing more — that is what
 * lets the same row drive the grid, the list and the count without any of them
 * asking a second question.
 *
 *   GET /files?module=&area=&kind=&day=&thumb=&q=
 *
 * An unreadable parameter is IGNORED, never an error: a filter arriving from a
 * stale bookmark or a hand-edited URL must narrow the hub or leave it alone, and
 * must never take it down.
 */
final readonly class FileFilter
{
    public const string THUMB_MADE = 'made';
    public const string THUMB_MISSING = '!made';

    public function __construct(
        public ?string $module = null,
        public ?string $area = null,
        public ?FileKindEnum $kind = null,
        public ?string $day = null,
        public ?string $thumb = null,
        public string $q = '',
    ) {
    }

    /**
     * @param array<string, mixed> $query typically $request->query->all()
     */
    public static function fromQuery(array $query): self
    {
        $kind = self::text($query['kind'] ?? null);
        $thumb = self::text($query['thumb'] ?? null);

        return new self(
            self::text($query['module'] ?? null),
            self::text($query['area'] ?? null),
            null !== $kind ? FileKindEnum::tryFrom($kind) : null,
            self::day(self::text($query['day'] ?? null)),
            \in_array($thumb, [self::THUMB_MADE, self::THUMB_MISSING], true) ? $thumb : null,
            self::text($query['q'] ?? null) ?? '',
        );
    }

    public function isEmpty(): bool
    {
        return null === $this->module
            && null === $this->area
            && null === $this->kind
            && null === $this->day
            && null === $this->thumb
            && '' === $this->q;
    }

    public function keeps(FileEntry $file): bool
    {
        if (null !== $this->module && $file->moduleSlug !== $this->module) {
            return false;
        }
        if (null !== $this->area && $file->areaSlug !== $this->area) {
            return false;
        }
        if (null !== $this->kind && $file->kind !== $this->kind) {
            return false;
        }
        if (null !== $this->day && $file->day() !== $this->day) {
            return false;
        }
        if (null !== $this->thumb && $file->thumbState->exists() !== (self::THUMB_MADE === $this->thumb)) {
            return false;
        }

        return $file->matches($this->q);
    }

    private static function text(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * A day is a calendar date and nothing else. Anything that is not one is
     * dropped rather than guessed at — a filter nobody can read is a filter
     * nobody meant.
     */
    private static function day(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false !== $parsed && $parsed->format('Y-m-d') === $value ? $value : null;
    }
}
