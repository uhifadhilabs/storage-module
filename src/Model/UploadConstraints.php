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

use Symfony\Component\Mime\MimeTypes;

/**
 * WHAT ONE TARGET TAKES — the rule the upload component states before anybody
 * drops anything.
 *
 * THE DESIGN'S ONE INSTRUCTION ABOUT THIS: the allowed kinds and the size limit
 * are stated up front, "never discovered by being refused". That only stays true
 * if the sentence on the zone and the guard behind the endpoint read the same
 * numbers, which is why this object is both — the component prints it and
 * {@see \Uhifadhi\Storage\Service\UploadService} enforces it.
 *
 * IT IS THE DEPLOYMENT'S RULE UNTIL A TARGET NARROWS IT. {@see from()} builds one
 * out of {@see EvidenceConstraints}, which is the installation's own configured
 * allowlist and cap; a target that takes less — a boundary import that wants one
 * GPX and not a photograph — states its own and gets it. A target cannot widen
 * past what the storage accepts, because the storage validates again on the way
 * in and would refuse what this promised.
 *
 * MAX FILES IS THIS OBJECT'S OWN. EvidenceConstraints has no such idea: it
 * guards ONE file and knows nothing about a queue. How many may be in flight at
 * once is a property of the door, not of the bytes.
 */
final readonly class UploadConstraints
{
    /**
     * The design's own figure, on the zone that ships with it: "10 files at a
     * time". A cap rather than no cap because a queue is drawn per file and a
     * folder dropped by accident should be refused before it is rendered.
     */
    public const int DEFAULT_MAX_FILES = 10;

    /**
     * @param list<string> $allowedMimeTypes the DETECTED types this target takes
     * @param int          $maxBytes         the largest single file
     * @param int          $maxFiles         how many may be queued at once
     */
    public function __construct(
        public array $allowedMimeTypes,
        public int $maxBytes,
        public int $maxFiles = self::DEFAULT_MAX_FILES,
    ) {
        if ([] === $allowedMimeTypes) {
            throw new \InvalidArgumentException('An empty allowlist would accept nothing at all.');
        }
        if ($maxBytes < 1) {
            throw new \InvalidArgumentException('A size cap below one byte would accept nothing at all.');
        }
        if ($maxFiles < 1) {
            throw new \InvalidArgumentException('A queue that holds no files would accept nothing at all.');
        }
    }

    /**
     * The deployment's own rules, as a target's default answer.
     *
     * A target that has no narrower opinion returns this and inherits every
     * later change to the installation's configuration for free.
     */
    public static function from(EvidenceConstraints $evidence, ?int $maxFiles = null): self
    {
        return new self($evidence->allowedMimeTypes, $evidence->maxBytes, $maxFiles ?? self::DEFAULT_MAX_FILES);
    }

    /**
     * THE WORDS ON THE ZONE — "jpg · png · heic · pdf · gpx".
     *
     * Extensions, not mime types, because that is what somebody looking at their
     * own files sees. Each is the FIRST extension symfony/mime knows for the
     * type, which is the same lookup {@see EvidenceConstraints::extensionFor()}
     * uses to name a stored key — so the line cannot promise a spelling the
     * storage would then file under another one.
     *
     * A type nothing can name is LEFT OFF rather than guessed at: such a file is
     * refused on the way in ({@see EvidenceConstraints::extensionFor()} throws),
     * and advertising it would be advertising a refusal.
     *
     * @return list<string>
     */
    public function extensions(): array
    {
        $extensions = [];
        foreach ($this->allowedMimeTypes as $mimeType) {
            $known = MimeTypes::getDefault()->getExtensions($mimeType);
            if ([] === $known || \in_array($known[0], $extensions, true)) {
                continue;
            }
            $extensions[] = $known[0];
        }

        return $extensions;
    }

    /**
     * What the file picker is handed. The MIME TYPES themselves rather than the
     * extensions above: `accept` is a filter for the operating system's dialog,
     * and giving it the types keeps it agreeing with the guard even where a
     * platform spells an extension differently.
     */
    public function accept(): string
    {
        return implode(',', $this->allowedMimeTypes);
    }

    /**
     * A null is ALLOWED, matching {@see EvidenceConstraints::allows()} exactly.
     * The two must agree: a component that refused what the storage would have
     * accepted would be inventing a rule nobody configured.
     */
    public function allows(?string $mimeType): bool
    {
        if (null === $mimeType) {
            return true;
        }

        return \in_array($mimeType, $this->allowedMimeTypes, true);
    }

    public function fits(int $byteSize): bool
    {
        return $byteSize <= $this->maxBytes;
    }
}
