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
use Uhifadhi\Storage\Enum\ThumbStateEnum;

/**
 * One file, as the hub knows it.
 *
 * A file is OWNER-BOUND: it belongs to a RECORD in a MODULE — an observation's
 * photograph, an incident's evidence, later a permit's document. That is why
 * $ownerLabel and $ownerUrl are not optional decoration but the file's identity;
 * a tile without its owner would be a lie about the model. The FILE NAME is
 * small print by comparison, which is exactly how files.css draws the two.
 *
 * Everything here is answered by the OWNING MODULE through FileSourceInterface.
 * This bundle adds only the two facts no module page knows — which named place
 * the bytes are in, and whether the one ~400px picture was made — and it adds
 * them from its own configuration, not from anything a module said.
 */
final readonly class FileEntry
{
    /** Derived rather than promoted: the three facts below are read OFF the others, so no caller can state them inconsistently. */
    public FileKindEnum $kind;
    public ThumbStateEnum $thumbState;
    public \DateTimeImmutable $arrivedAt;

    /**
     * @param string                  $key         the storage key — the file's identity everywhere, including in its URL
     * @param string                  $name        what to print: the file's own name, as the person who took it would recognise it
     * @param string                  $mimeType    the DETECTED type — never what an uploader claimed
     * @param int                     $byteSize    size of the original, in bytes
     * @param string                  $ownerLabel  the record this file belongs to, e.g. "OBS-0214"
     * @param string|null             $ownerUrl    the owning record's own page; null only where the module ships no such page yet
     * @param string                  $moduleSlug  the owning module, e.g. "patrols"
     * @param string                  $moduleLabel the owning module in the words a warden reads, e.g. "Patrols"
     * @param string|null             $areaSlug    the area whose files these are; null for something org-wide
     * @param string|null             $areaLabel   that area's name
     * @param \DateTimeImmutable|null $takenAt     the HANDSET's own clock. Null for a document, which has no such moment
     * @param \DateTimeImmutable      $arrivedAt   when it reached the platform — the only ordering that answers "has it synced"
     * @param string|null             $thumbKey    key of the ONE ~400px picture, or null where there is none
     * @param ThumbStateEnum|null     $thumbState  pass it where the module tracks a queue; derived from the kind otherwise
     * @param string|null             $caption     the caption belongs to the RECORD, not to the file; it is shown here, never edited here
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $mimeType,
        public int $byteSize,
        public string $ownerLabel,
        public ?string $ownerUrl,
        public string $moduleSlug,
        public string $moduleLabel,
        public ?string $areaSlug = null,
        public ?string $areaLabel = null,
        public ?\DateTimeImmutable $takenAt = null,
        ?\DateTimeImmutable $arrivedAt = null,
        public ?string $thumbKey = null,
        ?ThumbStateEnum $thumbState = null,
        public ?string $caption = null,
        ?FileKindEnum $kind = null,
    ) {
        if ('' === $key || '' === $name) {
            throw new \InvalidArgumentException('A file needs a key and a name.');
        }
        if ('' === $ownerLabel || '' === $moduleSlug || '' === $moduleLabel) {
            throw new \InvalidArgumentException(\sprintf('The file "%s" has no owner. Every file on this platform belongs to a record in a module; a file without one cannot be shown, because the hub would have nothing true to write on its tile.', $key));
        }
        if ($byteSize < 0) {
            throw new \InvalidArgumentException('A file cannot have a negative size.');
        }

        $this->kind = $kind ?? FileKindEnum::fromMimeType($mimeType);
        $this->thumbState = $thumbState ?? ThumbStateEnum::of($this->kind, $thumbKey);
        $this->arrivedAt = $arrivedAt ?? ($takenAt ?? new \DateTimeImmutable('@0'));
    }

    /**
     * The day this file is filed under.
     *
     * The HANDSET's day where there is one, never the day it uploaded: a patrol
     * out for three days syncs on the third, and its photographs still belong to
     * the days they were taken. A document has no such clock, so it sits under
     * the day it was filed.
     */
    public function day(): string
    {
        return ($this->takenAt ?? $this->arrivedAt)->format('Y-m-d');
    }

    public function hasThumbnail(): bool
    {
        return null !== $this->thumbKey;
    }

    /**
     * Grouping key for "the records that hold files": a record id is only
     * unique inside its own module.
     */
    public function ownerRef(): string
    {
        return $this->moduleSlug.'/'.$this->ownerLabel;
    }

    /**
     * Does this file answer to a free-text search?
     *
     * The file NAME and the OWNING RECORD's id, and nothing else — those are the
     * two things a person actually remembers. Deliberately not the caption: a
     * caption is the record's, not the file's.
     */
    public function matches(string $needle): bool
    {
        if ('' === $needle) {
            return true;
        }

        $haystack = mb_strtolower($this->name.' '.$this->ownerLabel);

        return str_contains($haystack, mb_strtolower($needle));
    }
}
