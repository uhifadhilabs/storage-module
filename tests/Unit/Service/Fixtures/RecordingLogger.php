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

namespace Uhifadhi\Storage\Tests\Unit\Service\Fixtures;

use Psr\Log\AbstractLogger;

/**
 * A logger that keeps what it was told, so a test can assert the sentence a
 * server operator will read in their log rather than merely that something was
 * logged.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string}> */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        // PSR-3 types the level as mixed; anything but the string levels the
        // interface's own constants define is recorded by its type, so a test can
        // never match one by accident.
        $this->records[] = ['level' => \is_string($level) ? $level : get_debug_type($level), 'message' => (string) $message];
    }

    /** @return list<string> */
    public function messagesAt(string $level): array
    {
        $messages = [];
        foreach ($this->records as $record) {
            if ($record['level'] === $level) {
                $messages[] = $record['message'];
            }
        }

        return $messages;
    }
}
