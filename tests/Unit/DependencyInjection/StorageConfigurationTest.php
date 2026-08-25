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

namespace UhifadhiLabs\Storage\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use UhifadhiLabs\Storage\DependencyInjection\StorageConfiguration;

final class StorageConfigurationTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        $builder = new TreeBuilder('storage');
        StorageConfiguration::define($builder->getRootNode());

        /** @var array<string, mixed> $processed */
        $processed = new Processor()->process($builder->buildTree(), ['storage' => $config]);

        return $processed;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function evidence(array $config = []): array
    {
        $evidence = $this->process($config)['evidence'] ?? null;
        self::assertIsArray($evidence);

        /** @var array<string, mixed> $evidence */
        return $evidence;
    }

    /**
     * The s3 sub-tree, narrowed once so the assertions below read as
     * assertions rather than as type gymnastics.
     *
     * @param array<string, mixed> $evidence
     *
     * @return array<string, mixed>
     */
    private function s3(array $evidence): array
    {
        $s3 = $evidence['s3'] ?? null;
        self::assertIsArray($s3);

        /** @var array<string, mixed> $s3 */
        return $s3;
    }

    /**
     * A host that installs the bundle and writes nothing gets a working,
     * PRIVATE, on-disk evidence store. Requiring configuration to be safe is
     * how deployments end up unsafe.
     */
    public function testAnUnconfiguredHostGetsAPrivateLocalStore(): void
    {
        $evidence = $this->evidence();

        self::assertSame('local', $evidence['adapter']);
        self::assertSame('%kernel.project_dir%/var/storage/evidence', $evidence['directory']);
        self::assertSame(12 * 1024 * 1024, $evidence['max_bytes']);
        self::assertSame(400, $evidence['thumbnail_long_edge']);
        self::assertSame(
            ['image/jpeg', 'image/png', 'image/heic', 'image/heif', 'image/webp'],
            $evidence['allowed_mime_types'],
        );
    }

    public function testADeploymentCanPointTheLocalStoreSomewhereElse(): void
    {
        self::assertSame('/srv/evidence', $this->evidence([
            'evidence' => ['directory' => '/srv/evidence'],
        ])['directory']);
    }

    public function testTheAdapterSwitchAcceptsS3(): void
    {
        $evidence = $this->evidence([
            'evidence' => [
                'adapter' => 's3',
                's3' => [
                    'endpoint' => 'https://fsn1.your-objectstorage.com',
                    'bucket' => 'uhifadhi-evidence',
                    'region' => 'fsn1',
                    'key' => 'ACCESS',
                    'secret' => 'SECRET',
                ],
            ],
        ]);

        $s3 = $this->s3($evidence);

        self::assertSame('s3', $evidence['adapter']);
        self::assertSame('https://fsn1.your-objectstorage.com', $s3['endpoint']);
        self::assertSame('uhifadhi-evidence', $s3['bucket']);
        self::assertSame('fsn1', $s3['region']);
        // Hetzner object storage addresses buckets by path, not by subdomain.
        self::assertTrue($s3['path_style_endpoint']);
    }

    public function testAnUnknownAdapterIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->evidence(['evidence' => ['adapter' => 'dropbox']]);
    }

    /**
     * Choosing s3 without saying WHERE is the failure that would otherwise
     * surface as a 500 on the first upload in production.
     */
    public function testChoosingS3WithoutABucketIsRefusedAtCompileTime(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->evidence([
            'evidence' => [
                'adapter' => 's3',
                's3' => ['endpoint' => 'https://fsn1.your-objectstorage.com'],
            ],
        ]);
    }

    public function testChoosingS3WithoutAnEndpointIsRefusedAtCompileTime(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->evidence([
            'evidence' => [
                'adapter' => 's3',
                's3' => ['bucket' => 'uhifadhi-evidence'],
            ],
        ]);
    }

    public function testADeploymentMayNarrowTheAllowlistAndTheSizeCap(): void
    {
        $evidence = $this->evidence([
            'evidence' => [
                'allowed_mime_types' => ['image/jpeg'],
                'max_bytes' => 2048,
            ],
        ]);

        self::assertSame(['image/jpeg'], $evidence['allowed_mime_types']);
        self::assertSame(2048, $evidence['max_bytes']);
    }

    public function testAnEmptyAllowlistIsRefusedBecauseItWouldAcceptNothing(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->evidence(['evidence' => ['allowed_mime_types' => []]]);
    }

    public function testANonPositiveSizeCapIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->evidence(['evidence' => ['max_bytes' => 0]]);
    }

    public function testTheTreeIsClosedToUnknownKeys(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->evidence(['evidence' => ['public_url' => 'https://cdn.example.test']]);
    }

    /**
     * The evidence storage is private BY CONSTRUCTION — there is no config key
     * that could make it public. That is the whole point of the named storage.
     */
    public function testThereIsNoWayToMakeEvidencePublic(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->evidence(['evidence' => ['visibility' => 'public']]);
    }
}
