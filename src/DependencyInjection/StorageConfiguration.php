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

namespace Uhifadhi\Storage\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Uhifadhi\Storage\Model\EvidenceConstraints;

/**
 * How a host configures the platform's storage, in config/packages/storage.yaml:
 *
 *   storage:
 *     evidence:
 *       adapter: local                                    # local | s3
 *       directory: '%kernel.project_dir%/var/storage/evidence'
 *       max_bytes: 12582912
 *       thumbnail_long_edge: 400
 *       allowed_mime_types: ['image/jpeg', …]
 *       s3:
 *         endpoint: '%env(STORAGE_S3_ENDPOINT)%'
 *         bucket:   '%env(STORAGE_S3_BUCKET)%'
 *         region:   '%env(STORAGE_S3_REGION)%'
 *         key:      '%env(STORAGE_S3_KEY)%'
 *         secret:   '%env(STORAGE_S3_SECRET)%'
 *
 * Two things are deliberately NOT configurable.
 *
 * There is no visibility key. The evidence storage is private by construction,
 * because "private unless someone remembers to say so" is how deployments end
 * up serving a carcass photograph to the open internet.
 *
 * There is no public_url key either, for the same reason: a public URL would
 * route around the permission seam entirely.
 *
 * Static so the tree is testable with a plain Processor and shared verbatim by
 * the bundle's configure() and its prependExtension().
 */
final class StorageConfiguration
{
    public const string ADAPTER_LOCAL = 'local';
    public const string ADAPTER_S3 = 's3';

    public static function define(NodeDefinition|ArrayNodeDefinition $root): void
    {
        if (!$root instanceof ArrayNodeDefinition) {
            throw new \LogicException('The storage root node must be an array node.');
        }

        $root
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('files')
                    ->info('The Files hub — the cross-module screen at /files. It needs SecurityBundle and Twig; where either is absent the screens are simply not registered. The widget framework is no longer a condition — uhifadhi/widget-module is a hard requirement, because the hub IS a widget dashboard.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Register the hub, the widget library, the file page and the settings page. On by default: a host that installed this bundle and a module that publishes files wants to be able to look at them.')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('settings_permission')
                            ->info('What “Where files go” asks for. Seeing where files are kept is seeing something about every file at once, so it rides on the deployment’s administrator permission — set it to the installation’s own Modules permission where there is one (`module.create`, with uhifadhi/team-module).')
                            ->defaultValue('ROLE_ADMIN')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('storage_label')
                            ->info('What an administrator calls the place files go — “Hetzner”, “This server”. The one place a proper noun is allowed, and it comes from the deployment, never from this bundle.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('storage_location')
                            ->info('Where that place physically is, as far as anybody here knows: “Falkenstein, Germany”, “the machine the site runs on”.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('evidence')
                    ->info('The private evidence storage: field photographs and anything else that must never be guessable by URL.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('adapter')
                            ->info('Where the bytes live. "local" for a directory on this machine, "s3" for any S3-compatible object storage (Hetzner is the production target).')
                            ->values([self::ADAPTER_LOCAL, self::ADAPTER_S3])
                            ->defaultValue(self::ADAPTER_LOCAL)
                        ->end()
                        ->scalarNode('directory')
                            ->info('Local adapter only. Outside the document root, always — nothing here may be reachable without passing the serving route.')
                            ->defaultValue('%kernel.project_dir%/var/storage/evidence')
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('max_bytes')
                            ->info('Largest file this deployment accepts as evidence.')
                            ->defaultValue(EvidenceConstraints::DEFAULT_MAX_BYTES)
                            ->min(1)
                        ->end()
                        ->integerNode('thumbnail_long_edge')
                            ->info('Long edge, in pixels, of the single JPEG preview generated beside each original.')
                            ->defaultValue(400)
                            ->min(1)
                        ->end()
                        ->arrayNode('allowed_mime_types')
                            ->info('The DETECTED types accepted. A deployment may narrow this; it may not widen it usefully, because a thumbnail engine still has to read the result.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultValue(EvidenceConstraints::DEFAULT_MIME_TYPES)
                            ->requiresAtLeastOneElement()
                        ->end()
                        ->arrayNode('s3')
                            ->info('S3-compatible object storage. Required when adapter is "s3".')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('endpoint')
                                    ->info('Full URL of the S3 endpoint, e.g. https://fsn1.your-objectstorage.com')
                                    ->defaultNull()
                                ->end()
                                ->scalarNode('bucket')->defaultNull()->end()
                                ->scalarNode('region')->defaultValue('us-east-1')->end()
                                ->scalarNode('key')->defaultNull()->end()
                                ->scalarNode('secret')->defaultNull()->end()
                                ->scalarNode('prefix')
                                    ->info('Optional path prefix inside the bucket, so one bucket can hold several deployments.')
                                    ->defaultValue('')
                                ->end()
                                ->booleanNode('path_style_endpoint')
                                    ->info('Address buckets as endpoint/bucket rather than bucket.endpoint. True by default: Hetzner and Minio both want path style, and only AWS itself really wants the other.')
                                    ->defaultTrue()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    // Caught HERE rather than at the first upload in production:
                    // an s3 deployment that never said where is a compile-time
                    // mistake, and it should read like one.
                    ->validate()
                        ->ifTrue(static function (array $evidence): bool {
                            if (self::ADAPTER_S3 !== ($evidence['adapter'] ?? self::ADAPTER_LOCAL)) {
                                return false;
                            }

                            $s3 = \is_array($evidence['s3'] ?? null) ? $evidence['s3'] : [];

                            return null === ($s3['bucket'] ?? null) || null === ($s3['endpoint'] ?? null);
                        })
                        ->thenInvalid('storage.evidence.adapter is "s3", so storage.evidence.s3.endpoint and storage.evidence.s3.bucket are both required.')
                    ->end()
                ->end()
            ->end()
        ;
    }
}
