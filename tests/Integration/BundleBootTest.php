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

namespace UhifadhiLabs\Storage\Tests\Integration;

use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\Visibility;
use League\FlysystemBundle\FlysystemBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use UhifadhiLabs\Storage\UhifadhiLabsStorageBundle;

/**
 * The smoke test: registering the bundle in a real kernel compiles a real
 * container, and the named storage the whole platform depends on exists in it.
 */
final class BundleBootTest extends KernelTestCase
{
    public function testTheBundleBootsInAHostKernel(): void
    {
        $kernel = self::bootKernel();

        self::assertArrayHasKey('UhifadhiLabsStorageBundle', $kernel->getBundles());
        self::assertInstanceOf(
            UhifadhiLabsStorageBundle::class,
            $kernel->getBundle('UhifadhiLabsStorageBundle'),
        );
    }

    /**
     * Config lives under "storage:", not the class-derived
     * "uhifadhi_labs_storage:" — the alias is part of the host contract.
     */
    public function testItsConfigurationIsKeyedByTheStorageAlias(): void
    {
        $kernel = self::bootKernel();

        self::assertSame('storage', $kernel->getBundle('UhifadhiLabsStorageBundle')
            ->getContainerExtension()?->getAlias());
    }

    /**
     * Zero-config storages: the bundle PREPENDS its flysystem block, so a host
     * never writes config/packages/flysystem.yaml to get an evidence store.
     *
     * flysystem-bundle names the service after the storage
     * (FlysystemExtension::createStoragesDefinitions() calls
     * `$container->setDefinition($storageName, …)`), which is why the storage
     * is called "storage.evidence" — bundle alias first, as Symfony's
     * reusable-bundle best practice requires of every service id.
     */
    public function testItDeclaresTheEvidenceStorageWithoutHostConfiguration(): void
    {
        self::bootKernel();

        self::assertTrue(self::getContainer()->has('storage.evidence'));
        self::assertInstanceOf(FilesystemOperator::class, self::getContainer()->get('storage.evidence'));
    }

    /**
     * The evidence store is PRIVATE, always. Flysystem's local adapter takes
     * its default visibility from the storage's directory_visibility, and
     * flysystem-bundle's AsyncAws builder defaults that to PUBLIC when it is
     * not set (AsyncAwsAdapterDefinitionBuilder::createAdapter():
     * `$defaultVisibilityForDirectories ?? Visibility::PUBLIC`). The bundle
     * therefore sets it explicitly rather than inheriting a default that flips
     * meaning when a deployment switches to S3.
     */
    public function testTheEvidenceStorageIsPrivateOnBothVisibilityAxes(): void
    {
        // Read from the DEFINITION rather than the booted container: the
        // visibility is constructor config on League\Flysystem\Filesystem
        // (argument 1), and a compiled service no longer reports it.
        $config = $this->compile()->getDefinition('storage.evidence')->getArgument(1);

        self::assertIsArray($config);
        self::assertSame(Visibility::PRIVATE, $config['visibility']);
        self::assertSame(Visibility::PRIVATE, $config['directory_visibility']);
    }

    /**
     * The adapter switch, all the way down to the definitions it produces.
     *
     * Worth pinning at this level because the S3 path is the one nobody
     * exercises locally: it has to survive to production first try, and the
     * three things that could silently be wrong — which adapter class is used,
     * that the client is a real service the adapter can reference, and that
     * path-style addressing is on — are all visible here.
     */
    public function testChoosingS3WiresTheAsyncAwsAdapterOntoARealClientService(): void
    {
        $builder = $this->compile([
            'evidence' => [
                'adapter' => 's3',
                's3' => [
                    'endpoint' => 'https://fsn1.your-objectstorage.com',
                    'bucket' => 'uhifadhi-evidence',
                    'region' => 'fsn1',
                    'key' => '%env(STORAGE_S3_KEY)%',
                    'secret' => '%env(STORAGE_S3_SECRET)%',
                ],
            ],
        ]);

        $adapter = $builder->getDefinition('flysystem.adapter.storage.evidence');
        self::assertSame(AsyncAwsS3Adapter::class, $adapter->getClass());

        // The asyncaws builder does `new Reference($options['client'])`, so the
        // client must exist as a service or the container fails to compile.
        $client = $adapter->getArgument(0);
        self::assertInstanceOf(Reference::class, $client);
        self::assertSame('storage.s3_client', (string) $client);
        self::assertTrue($builder->hasDefinition('storage.s3_client'));
        self::assertSame('uhifadhi-evidence', $adapter->getArgument(1));

        $options = $builder->getDefinition('storage.s3_client')->getArgument(0);
        self::assertIsArray($options);
        self::assertSame('https://fsn1.your-objectstorage.com', $options['endpoint']);
        self::assertSame('fsn1', $options['region']);
        // Hetzner addresses buckets by path; without this the client invents a
        // bucket.endpoint hostname that does not resolve.
        self::assertSame('true', $options['pathStyleEndpoint']);

        // Credentials stay as env PLACEHOLDERS: resolved at runtime, so the
        // cached container is never a file full of secrets.
        self::assertSame('%env(STORAGE_S3_KEY)%', $options['accessKeyId']);
        self::assertSame('%env(STORAGE_S3_SECRET)%', $options['accessKeySecret']);
    }

    /** The default path stays on disk, and no S3 client is conjured for it. */
    public function testTheLocalAdapterIsUsedByDefaultAndNoS3ClientIsDefined(): void
    {
        $builder = $this->compile();

        self::assertSame(
            LocalFilesystemAdapter::class,
            $builder->getDefinition('flysystem.adapter.storage.evidence')->getClass(),
        );
        self::assertFalse($builder->hasDefinition('storage.s3_client'));
    }

    /**
     * Compile the two bundles by hand, in the order the kernel does it.
     *
     * @param array<string, mixed> $storageConfig
     */
    private function compile(array $storageConfig = []): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.debug', false);
        $builder->setParameter('kernel.project_dir', \dirname(__DIR__, 2));
        $builder->setParameter('kernel.bundles', ['FrameworkBundle' => FrameworkBundle::class]);

        $flysystem = new FlysystemBundle();
        $storage = new UhifadhiLabsStorageBundle();
        foreach ([$flysystem, $storage] as $bundle) {
            if (null !== $extension = $bundle->getContainerExtension()) {
                $builder->registerExtension($extension);
            }
        }
        // FlysystemBundle::build() is what registers the adapter definition
        // builders on its extension. Without it the config tree knows only the
        // deprecated "adapter:"/"options:" pair and rejects "local:".
        $flysystem->build($builder);
        $storage->build($builder);

        if ([] !== $storageConfig) {
            $builder->prependExtensionConfig('storage', $storageConfig);
        }

        // prepend() then load(), in the order MergeExtensionConfigurationPass
        // runs them: the storage bundle CONTRIBUTES its flysystem block during
        // prepend, and flysystem's own extension turns that into definitions.
        $storageExtension = $storage->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $storageExtension);
        $storageExtension->prepend($builder);
        $storageExtension->load($builder->getExtensionConfig('storage') ?: [[]], $builder);

        $flysystem->getContainerExtension()?->load($builder->getExtensionConfig('flysystem'), $builder);

        return $builder;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // The framework's debug error handler is registered during the test and
        // never popped; PHPUnit flags that as risky. Pop whatever is left.
        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }
}
