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

/**
 * A named place the organisation keeps its files.
 *
 * Plain language, deliberately: "where the files go", never "the configured
 * Flysystem adapter". The one place a proper noun is allowed is the name of a
 * storage the organisation actually bought, and that name comes from the installation's
 * own configuration — this bundle never invents a vendor.
 *
 * Everything here is READ-ONLY TRUTH from config/packages/storage.yaml. The
 * settings page states what is configured; it does not offer to change it,
 * because changing where evidence lives is a deployment decision and not a
 * form.
 */
final readonly class StoragePlace
{
    /**
     * @param string      $id      "evidence" — the storage's name in the installation's configuration
     * @param string      $label   what an administrator calls it: "Hetzner", "This server"
     * @param string      $kind    "s3" or "local"; the pill's modifier in files.css
     * @param string      $what    one line saying what sort of place it is
     * @param string|null $where   where it physically is, as far as configuration knows
     * @param bool        $current whether new files go here — there is exactly one such place
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $kind,
        public string $what,
        public ?string $where = null,
        public bool $current = true,
    ) {
    }

    public function isObjectStorage(): bool
    {
        return 's3' === $this->kind;
    }
}
