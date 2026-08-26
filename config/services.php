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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use UhifadhiLabs\Storage\Model\EvidenceConstraints;
use UhifadhiLabs\Storage\Registry\FileRegistry;
use UhifadhiLabs\Storage\Registry\FileSourceInterface;
use UhifadhiLabs\Storage\Security\EvidenceAccessDecider;
use UhifadhiLabs\Storage\Service\EvidenceStorage;
use UhifadhiLabs\Storage\Service\FilesSurface;
use UhifadhiLabs\Storage\Service\StorageSettings;
use UhifadhiLabs\Storage\Thumbnail\GdThumbnailer;
use UhifadhiLabs\Storage\Thumbnail\ImagickThumbnailer;
use UhifadhiLabs\Storage\Thumbnail\ThumbnailGenerator;

/*
 * The bundle's static service wiring.
 *
 * PHP (not YAML) on purpose: a reusable bundle must not force symfony/yaml onto
 * hosts, and FQCN references stay refactor-safe and phpstan-checked. Imported by
 * UhifadhiLabsStorageBundle::loadExtension(), which keeps only the config-DRIVEN
 * definitions (the S3 client, and the controller behind its security guard).
 *
 * Everything here is defined EXPLICITLY — no autowire(), no autoconfigure(), and
 * ids prefixed with the bundle alias — because this bundle is installed by other
 * projects via Composer, which is what Symfony calls a reusable bundle:
 *
 *   "Services should not use autowiring or autoconfiguration. Instead, all
 *    services should be defined explicitly."
 *   "If the bundle defines services, they must be prefixed with the bundle alias."
 *   — https://symfony.com/doc/current/bundles/best_practices.html
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    /*
     * The two thumbnail engines, in preference order. BOTH are registered on
     * every host, unconditionally, and neither definition touches the extension
     * it wraps: each answers isAvailable() at runtime, so a container compiled
     * on a machine with Imagick still works on one without it. Deciding this at
     * compile time would bake one machine's PHP build into a cached container.
     */
    $services->set('storage.thumbnailer.imagick', ImagickThumbnailer::class);
    $services->set('storage.thumbnailer.gd', GdThumbnailer::class);

    $services->set('storage.thumbnail_generator', ThumbnailGenerator::class)
        ->args([
            // Imagick first: it resamples better and, where the system
            // ImageMagick has libheif, it is the only one that reads HEIC.
            [service('storage.thumbnailer.imagick'), service('storage.thumbnailer.gd')],
            param('storage.evidence.thumbnail_long_edge'),
        ]);

    $services->set('storage.evidence_constraints', EvidenceConstraints::class)
        ->args([
            param('storage.evidence.allowed_mime_types'),
            param('storage.evidence.max_bytes'),
        ]);
    // Callers that want to validate BEFORE committing to an upload need a
    // handle on the same rules the storage applies, so the guard is reachable
    // by type as well as by id.
    $services->alias(EvidenceConstraints::class, 'storage.evidence_constraints');

    /*
     * "storage.evidence" is the flysystem storage this bundle prepends. The
     * name IS the service id: flysystem-bundle's extension registers each
     * storage under the name it was given —
     * FlysystemExtension::createStoragesDefinitions() does
     * `$container->setDefinition($storageName, …)` — which is also why the
     * storage is named with the bundle alias in front, as the reusable-bundle
     * rule above requires of every id.
     */
    $services->set('storage.evidence_storage', EvidenceStorage::class)
        ->args([
            service('storage.evidence'),
            service('storage.evidence_constraints'),
            service('storage.thumbnail_generator'),
        ]);
    $services->alias(EvidenceStorage::class, 'storage.evidence_storage');

    /*
     * The permission seam. The iterator is EMPTY on a host that has installed
     * no module yet — and an empty iterator denies everything, which is the
     * intended reading (EvidenceAccessDecider).
     */
    $services->set('storage.evidence_access_decider', EvidenceAccessDecider::class)
        ->args([tagged_iterator('uhifadhi.evidence_access_voter')]);
    $services->alias(EvidenceAccessDecider::class, 'storage.evidence_access_decider');

    /*
     * THE CROSS-MODULE FILE REGISTRY — the Files hub's whole supply.
     *
     * The same shape as the permission seam above and for the same reason: this
     * bundle has no idea what an observation or an incident is, so the files on
     * the hub are the ones OWNING MODULES handed over, each already carrying its
     * owner. The iterator is empty on a host that has installed no module yet,
     * and an empty hub is the correct reading of that rather than an error.
     */
    $services->set('storage.file_registry', FileRegistry::class)
        ->args([tagged_iterator(FileSourceInterface::TAG)]);
    $services->alias(FileRegistry::class, 'storage.file_registry');

    /*
     * "Where files go", answered from this deployment's own configuration. Every
     * argument is a parameter set by loadExtension() from the config tree, so the
     * settings page cannot show a fact that is not true here.
     */
    $services->set('storage.settings', StorageSettings::class)
        ->args([
            service('storage.file_registry'),
            param('storage.evidence.adapter'),
            param('storage.files.storage_label'),
            param('storage.files.storage_location'),
            param('storage.evidence.allowed_mime_types'),
            param('storage.evidence.max_bytes'),
            param('storage.evidence.thumbnail_long_edge'),
        ]);
    $services->alias(StorageSettings::class, 'storage.settings');

    $services->set('storage.files_surface', FilesSurface::class)
        ->args([service('storage.file_registry'), service('storage.settings')]);
    $services->alias(FilesSurface::class, 'storage.files_surface');
};
