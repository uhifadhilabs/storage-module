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

namespace UhifadhiLabs\Storage;

use AsyncAws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\Visibility;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use UhifadhiLabs\Storage\Controller\EvidenceController;
use UhifadhiLabs\Storage\DependencyInjection\StorageConfiguration;
use UhifadhiLabs\Storage\Model\EvidenceConstraints;
use UhifadhiLabs\Storage\Security\EvidenceAccessVoterInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Storage — the platform's file-storage machinery.
 *
 * MECHANISM ONLY. This bundle owns no entities and no screens: the photo
 * records stay in the modules that own them, because only those modules know
 * what a photograph is attached to. What lives here is the part every module
 * would otherwise re-implement, slightly differently each time: the named
 * storages, the validated evidence API, the thumbnails, and the one
 * authenticated route by which any of it comes back out.
 *
 * Zero-config: registering the bundle declares the private "storage.evidence"
 * storage, so no host writes a flysystem.yaml to get one.
 */
final class UhifadhiLabsStorageBundle extends AbstractBundle
{
    /** Config lives under "storage:", not the class-derived "uhifadhi_labs_storage:". */
    protected string $extensionAlias = 'storage';

    public function configure(DefinitionConfigurator $definition): void
    {
        StorageConfiguration::define($definition->rootNode());
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('flysystem')) {
            // No flysystem in this kernel: say so where a developer will read
            // it, rather than failing later on a missing "storage.evidence".
            throw new \LogicException('UhifadhiLabsStorageBundle needs league/flysystem-bundle. Register FlysystemBundle in config/bundles.php.');
        }

        $evidence = $this->evidenceConfig($builder);

        /*
         * The evidence storage, declared FOR the host.
         *
         * Written in flysystem-bundle's discoverable format ("local:" / "asyncaws:"
         * as the adapter key) and NOT the older `adapter:` + `options:` pair: that
         * pair is deprecated since flysystem-bundle 3.5, and its Configuration
         * marks it so ("DEPRECATED: Use the new config format instead"), which
         * would surface as a deprecation in every host that boots.
         *
         * Both visibility axes are set explicitly. That is not belt-and-braces:
         * the AsyncAws adapter builder defaults directory visibility to PUBLIC
         * when it is not given one —
         *   `$defaultVisibilityForDirectories ?? Visibility::PUBLIC`
         *   (AsyncAwsAdapterDefinitionBuilder::createAdapter())
         * — while the Local builder defaults it to PRIVATE. Leaving it unset
         * would therefore change the meaning of "evidence" the day a deployment
         * switched from local to S3, in the direction nobody wants.
         */
        $container->extension('flysystem', [
            'storages' => [
                'storage.evidence' => [
                    ...$this->adapterConfig($evidence),
                    'visibility' => Visibility::PRIVATE,
                    'directory_visibility' => Visibility::PRIVATE,
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $evidence = self::stringKeyed($config['evidence'] ?? null);

        /*
         * The guard's rules and the thumbnail size, as parameters for the
         * services in config/services.php that need them.
         *
         * The config tree has already type-checked and defaulted all three;
         * these narrowings are for the static analyser, which sees only
         * array<string, mixed> coming out of an extension.
         */
        $maxBytes = $evidence['max_bytes'] ?? null;
        $builder->setParameter('storage.evidence.max_bytes', \is_int($maxBytes) ? $maxBytes : EvidenceConstraints::DEFAULT_MAX_BYTES);

        $allowed = $evidence['allowed_mime_types'] ?? null;
        $builder->setParameter('storage.evidence.allowed_mime_types', \is_array($allowed) && [] !== $allowed ? array_values($allowed) : EvidenceConstraints::DEFAULT_MIME_TYPES);

        $longEdge = $evidence['thumbnail_long_edge'] ?? null;
        $builder->setParameter('storage.evidence.thumbnail_long_edge', \is_int($longEdge) && $longEdge > 0 ? $longEdge : 400);

        // Static service wiring lives in a PHP config file (see config/services.php
        // for why PHP, not YAML). loadExtension keeps only the config-DRIVEN bits.
        $container->import('../config/services.php');

        $services = $container->services();

        /*
         * The S3 client the asyncaws adapter references BY SERVICE ID (its
         * builder does `new Reference($options['client'])`), so it has to be a
         * real service and this is where it is defined.
         *
         * Credentials arrive as env placeholders and stay placeholders: they are
         * resolved at runtime, never baked into the compiled container, so a
         * cached container is not a file full of secrets.
         */
        if (StorageConfiguration::ADAPTER_S3 === ($evidence['adapter'] ?? StorageConfiguration::ADAPTER_LOCAL)) {
            if (!class_exists(AsyncAwsS3Adapter::class)) {
                throw new \LogicException('storage.evidence.adapter is "s3". Run: composer require league/flysystem-async-aws-s3');
            }

            $s3 = self::stringKeyed($evidence['s3'] ?? null);

            $services->set('storage.s3_client', S3Client::class)
                ->args([[
                    // Key names are AsyncAws\Core\Configuration's own option
                    // constants — accessKeyId / accessKeySecret, not the AWS
                    // SDK's key / secret.
                    'endpoint' => $s3['endpoint'] ?? null,
                    'accessKeyId' => $s3['key'] ?? null,
                    'accessKeySecret' => $s3['secret'] ?? null,
                    'region' => $s3['region'] ?? 'us-east-1',
                    // Hetzner and Minio address buckets by path. Without this,
                    // the client invents a bucket.endpoint hostname that does
                    // not resolve.
                    'pathStyleEndpoint' => ($s3['path_style_endpoint'] ?? true) ? 'true' : 'false',
                ]]);
        }

        /*
         * The serving route is registered ONLY inside this guard.
         *
         * It is the only way evidence leaves the system, and it must never
         * exist unprotected: without symfony/security there is no token storage
         * to read a user from, so a voter could not tell a signed-in ranger from
         * a stranger. A host in that state gets NO route at all rather than one
         * that hands out photographs.
         *
         * The guard asks whether SecurityBundle is actually in the kernel, read
         * from kernel.bundles. Two other checks look right and are not:
         * hasExtension('security') cannot be used while an extension is loading,
         * because the builder is then a restricted
         * MergeExtensionConfigurationContainerBuilder that does not expose other
         * extensions; and interface_exists() only proves a class is autoloadable
         * — security-core is one of this bundle's DEV dependencies, so it
         * autoloads in our own test runs even when SecurityBundle is absent, and
         * the service would then reference a security.* id that does not exist.
         * FrameworkExtension reads kernel.bundles for exactly this reason.
         */
        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];
        $hasSecurity = \is_array($bundles) && isset($bundles['SecurityBundle']);
        $builder->setParameter('storage.evidence.serving_route', $hasSecurity);

        if ($hasSecurity) {
            // The id is the FQCN because that is how Symfony resolves a
            // controller named by an #[Route] on an invokable class, and public
            // because the router fetches it from the container directly.
            $services->set(EvidenceController::class)
                ->args([
                    service('storage.evidence_storage'),
                    service('storage.evidence_access_decider'),
                    service('security.token_storage'),
                ])
                ->public();
        }
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        /*
         * A host is autoconfigured even though this bundle is not, so declaring
         * the interface here saves every MODULE from tagging by hand — a module
         * that forgot the tag would silently lose access to its own evidence,
         * which is a confusing way to find out.
         *
         * Modules shipped as reusable bundles are NOT autoconfigured and must
         * still tag explicitly; see EvidenceAccessVoterInterface.
         */
        $container->registerForAutoconfiguration(EvidenceAccessVoterInterface::class)
            ->addTag('uhifadhi.evidence_access_voter');
    }

    /**
     * The bundle's own configuration, read back during prepend().
     *
     * prependExtension() runs before load(), so the processed config is not
     * handed over — getExtensionConfig() returns the raw, unmerged arrays and
     * the tree is applied here to get defaults. This is the documented way for
     * a bundle to act on its own configuration while prepending.
     *
     * @return array<string, mixed>
     */
    private function evidenceConfig(ContainerBuilder $builder): array
    {
        $tree = new TreeBuilder($this->extensionAlias);
        StorageConfiguration::define($tree->getRootNode());

        /** @var array<string, mixed> $processed */
        $processed = new Processor()->process($tree->buildTree(), $builder->getExtensionConfig($this->extensionAlias));

        return self::stringKeyed($processed['evidence'] ?? null);
    }

    /**
     * Narrow a config sub-tree to the shape the rest of this class relies on.
     * The tree guarantees it already; the analyser sees only mixed.
     *
     * @return array<string, mixed>
     */
    private static function stringKeyed(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $narrowed = [];
        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                $narrowed[$key] = $item;
            }
        }

        return $narrowed;
    }

    /**
     * The adapter half of the storage declaration.
     *
     * @param array<string, mixed> $evidence
     *
     * @return array<string, mixed>
     */
    private function adapterConfig(array $evidence): array
    {
        if (StorageConfiguration::ADAPTER_S3 !== ($evidence['adapter'] ?? StorageConfiguration::ADAPTER_LOCAL)) {
            return [
                'local' => [
                    'directory' => \is_string($evidence['directory'] ?? null)
                        ? $evidence['directory']
                        : '%kernel.project_dir%/var/storage/evidence',
                ],
            ];
        }

        $s3 = self::stringKeyed($evidence['s3'] ?? null);

        return [
            'asyncaws' => [
                // The SERVICE ID defined in loadExtension(), which is what the
                // asyncaws builder expects here.
                'client' => 'storage.s3_client',
                'bucket' => $s3['bucket'] ?? '',
                'prefix' => $s3['prefix'] ?? '',
            ],
        ];
    }
}
