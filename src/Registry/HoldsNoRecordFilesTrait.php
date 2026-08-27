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

namespace UhifadhiLabs\Storage\Registry;

use UhifadhiLabs\Storage\Model\FileEntry;

/**
 * THE DEFAULT ANSWER to {@see FileSourceInterface::filesForRecord()}: nothing.
 *
 * PHP interfaces carry no bodies, so this is where "a module that does not
 * address its files by record is still a complete source" is written down. A
 * source `use`s this and is done; one that has photographs another module needs
 * to show implements the method itself.
 *
 * Answering with an empty list is a FACT, not a failure — "this module holds no
 * files for that record" is exactly what a report flow filed from a record with
 * no photographs should be told.
 */
trait HoldsNoRecordFilesTrait
{
    /**
     * @return iterable<FileEntry>
     */
    public function filesForRecord(string $source, string $recordUuid): iterable
    {
        return [];
    }
}
