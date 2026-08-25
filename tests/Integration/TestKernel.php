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

use League\FlysystemBundle\FlysystemBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use UhifadhiLabs\Storage\Service\EvidenceStorage;
use UhifadhiLabs\Storage\Tests\Integration\Fixtures\StubEvidenceVoter;
use UhifadhiLabs\Storage\UhifadhiLabsStorageBundle;

/**
 * Smallest possible host app: framework + security + flysystem + storage. No
 * database and no doctrine anywhere — this bundle owns no entities, and that
 * absence is part of its charter (README §Charter).
 *
 * The evidence store writes into a throwaway directory: the round-trip tests
 * assert that real bytes landed, so a mock filesystem would be testing itself.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public static function evidenceDirectory(): string
    {
        return sys_get_temp_dir().'/storage-module-tests/evidence';
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new FlysystemBundle();
        yield new UhifadhiLabsStorageBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'router' => ['utf8' => true],
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            // loginUser() needs a stateful firewall, which needs a session; the
            // mock file storage is the documented choice for the test env.
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        // A minimal but REAL security setup: the serving route reads the user
        // from the token storage, so there has to be a genuine one to read.
        $container->extension('security', [
            'providers' => [
                'app_users' => ['memory' => ['users' => ['ranger@example.test' => ['password' => 'x', 'roles' => ['ROLE_USER']]]]],
            ],
            'firewalls' => [
                'main' => ['lazy' => true, 'provider' => 'app_users'],
            ],
        ]);

        $container->extension('storage', [
            'evidence' => [
                'adapter' => 'local',
                'directory' => self::evidenceDirectory(),
            ],
        ]);

        // The OWNING MODULE's voter, played by a fixture. Tagged by hand — a
        // reusable-bundle test kernel does not autoconfigure, exactly as the
        // real patrol/incident bundles will tag their own.
        $container->services()
            ->set(StubEvidenceVoter::class)
            ->tag('uhifadhi.evidence_access_voter');

        // Public alias so the round-trip tests can reach the bundle's private
        // service. The serving route references it too, but a test needs a
        // handle of its own.
        $container->services()
            ->alias('test_public.'.EvidenceStorage::class, 'storage.evidence_storage')
            ->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // Mounted exactly as a host mounts it: the bundle's controller carries
        // its own #[Route], so the host imports the directory.
        $controllers = \dirname(__DIR__, 2).'/src/Controller/';
        if (is_dir($controllers)) {
            $routes->import($controllers, 'attribute');
        }
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/storage-module-tests/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/storage-module-tests/log';
    }
}
