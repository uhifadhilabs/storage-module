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
 *   GET /files?module=&area=&kind=&day=&backend=&thumb=&q=&view=
 *
 * FOUR OF THEM ARE DROPDOWNS AND TWO ARE PILLS, and the difference is not
 * decoration: module, area, day and backend are things a deployment DEFINES and
 * they grow without bound, so each collapses into one chip carrying its own
 * counts; kind and thumbnail are fixed sets of three or four a person clicks
 * constantly, so they stay open. Every option is an ordinary link built from
 * this object with one key replaced — {@see self::withModule()} and its
 * siblings — which is why the row needs no scripting to filter.
 *
 * VIEW IS NOT A NARROWING. Thumbnails or a list is one result set in two
 * shapes, so it rides in the query (a narrowed hub stays linkable in the shape
 * it was read in) but it is absent from {@see self::isEmpty()} and it never
 * decides which files answer.
 *
 * An unreadable parameter is IGNORED, never an error: a filter arriving from a
 * stale bookmark or a hand-edited URL must narrow the hub or leave it alone, and
 * must never take it down.
 */
final readonly class FileFilter
{
    public const string THUMB_MADE = 'made';
    public const string THUMB_MISSING = '!made';

    public const string VIEW_GRID = 'grid';
    public const string VIEW_LIST = 'list';

    public function __construct(
        public ?string $module = null,
        public ?string $area = null,
        public ?FileKindEnum $kind = null,
        public ?string $day = null,
        public ?string $backend = null,
        public ?string $thumb = null,
        public string $q = '',
        public string $view = self::VIEW_GRID,
    ) {
    }

    /**
     * @param array<string, mixed> $query typically $request->query->all()
     */
    public static function fromQuery(array $query): self
    {
        $kind = self::text($query['kind'] ?? null);
        $thumb = self::text($query['thumb'] ?? null);
        $view = self::text($query['view'] ?? null);

        return new self(
            self::text($query['module'] ?? null),
            self::text($query['area'] ?? null),
            null !== $kind ? FileKindEnum::tryFrom($kind) : null,
            self::day(self::text($query['day'] ?? null)),
            self::text($query['backend'] ?? null),
            \in_array($thumb, [self::THUMB_MADE, self::THUMB_MISSING], true) ? $thumb : null,
            self::text($query['q'] ?? null) ?? '',
            self::VIEW_LIST === $view ? self::VIEW_LIST : self::VIEW_GRID,
        );
    }

    /**
     * This filter as the query string that reproduces it — the href of every
     * option in the row, and the hidden fields the search box carries.
     *
     * A choice nobody made is absent rather than empty, so an untouched hub is
     * plain `/files` and a bookmark says only what was actually chosen.
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        $query = array_filter([
            'module' => $this->module,
            'area' => $this->area,
            'kind' => $this->kind?->value,
            'day' => $this->day,
            'backend' => $this->backend,
            'thumb' => $this->thumb,
            'q' => '' === $this->q ? null : $this->q,
            'view' => self::VIEW_GRID === $this->view ? null : $this->view,
        ], static fn (?string $value): bool => null !== $value);

        ksort($query);

        return $query;
    }

    public function withModule(?string $module): self
    {
        return new self($module, $this->area, $this->kind, $this->day, $this->backend, $this->thumb, $this->q, $this->view);
    }

    public function withArea(?string $area): self
    {
        return new self($this->module, $area, $this->kind, $this->day, $this->backend, $this->thumb, $this->q, $this->view);
    }

    public function withKind(?FileKindEnum $kind): self
    {
        return new self($this->module, $this->area, $kind, $this->day, $this->backend, $this->thumb, $this->q, $this->view);
    }

    public function withDay(?string $day): self
    {
        return new self($this->module, $this->area, $this->kind, $day, $this->backend, $this->thumb, $this->q, $this->view);
    }

    public function withBackend(?string $backend): self
    {
        return new self($this->module, $this->area, $this->kind, $this->day, $backend, $this->thumb, $this->q, $this->view);
    }

    public function withThumb(?string $thumb): self
    {
        return new self($this->module, $this->area, $this->kind, $this->day, $this->backend, $thumb, $this->q, $this->view);
    }

    public function withView(string $view): self
    {
        return new self($this->module, $this->area, $this->kind, $this->day, $this->backend, $this->thumb, $this->q, $view);
    }

    public function isEmpty(): bool
    {
        return null === $this->module
            && null === $this->area
            && null === $this->kind
            && null === $this->day
            && null === $this->backend
            && null === $this->thumb
            && '' === $this->q;
    }

    /**
     * @param string|null $place the named storage this file's bytes are in. A
     *                           FILE DOES NOT KNOW — the installation's
     *                           configuration answers it, so whoever holds that
     *                           configuration hands the answer in beside the file
     */
    public function keeps(FileEntry $file, ?string $place = null): bool
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
        if (null !== $this->backend && $place !== $this->backend) {
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
