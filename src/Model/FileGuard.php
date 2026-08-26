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

namespace UhifadhiLabs\Storage\Model;

use UhifadhiLabs\Storage\Enum\GuardStateEnum;

/**
 * The owning record's answer about one file, IN THE MODULE'S OWN WORDS.
 *
 * The title and the sentence are the module's, not this bundle's, because the
 * reason a file cannot be removed is a fact about an incident or a patrol and
 * storage would only be able to paraphrase it badly. The hub renders whatever
 * it is handed.
 */
final readonly class FileGuard
{
    public function __construct(
        public GuardStateEnum $state,
        public string $title,
        public string $text,
    ) {
        if ('' === $title || '' === $text) {
            throw new \InvalidArgumentException('A guard has to say what it is and why; an empty answer is not one.');
        }
    }

    /**
     * The answer a hub falls back to when NO source claims the file.
     *
     * Locked rather than denied, and deliberately: an unclaimed key is one no
     * installed module admits to owning, and the honest thing to say is that
     * nothing here can authorise removing it — not that this particular person
     * is the problem.
     */
    public static function unclaimed(): self
    {
        return new self(
            GuardStateEnum::Locked,
            'No module claims this file',
            'Nothing installed on this platform admits to owning it, so nothing can say whether it may be removed. Until the module that wrote it is installed, the file is kept and left alone.',
        );
    }

    public function offersRemoval(): bool
    {
        return $this->state->offersRemoval();
    }

    public function needsReason(): bool
    {
        return GuardStateEnum::Reason === $this->state;
    }
}
