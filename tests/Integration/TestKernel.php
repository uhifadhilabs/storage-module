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

namespace Uhifadhi\Storage\Tests\Integration;

use League\FlysystemBundle\FlysystemBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Uhifadhi\Service\WidgetEndpoint;
use Uhifadhi\Service\WidgetService;
use Uhifadhi\Storage\Registry\FileRegistry;
use Uhifadhi\Storage\Registry\FileSourceInterface;
use Uhifadhi\Storage\Service\EvidenceStorage;
use Uhifadhi\Storage\Tests\Integration\Fixtures\StubEvidenceVoter;
use Uhifadhi\Storage\Tests\Integration\Fixtures\StubFileSource;
use Uhifadhi\Storage\UhifadhiStorageBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Smallest possible host app: framework + security + flysystem + storage. No
 * database and no doctrine anywhere — this bundle owns no entities, and that
 * absence is part of its charter (docs/charter.md).
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
        // Twig, because the Files hub is four screens. The bundle registers them
        // only where TwigBundle, SecurityBundle and the host's widget framework
        // are all present, and this kernel is what "all present" looks like.
        yield new TwigBundle();
        yield new FlysystemBundle();
        yield new UhifadhiStorageBundle();
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
            // The file page's removal form carries a token, so there has to be a
            // real manager minting it.
            'csrf_protection' => true,
        ]);

        // A minimal but REAL security setup: the serving route reads the user
        // from the token storage, so there has to be a genuine one to read.
        $container->extension('security', [
            'providers' => [
                'app_users' => ['memory' => ['users' => [
                    'ranger@example.test' => ['password' => 'x', 'roles' => ['ROLE_USER']],
                    // "Where files go" rides on the deployment's administrator
                    // permission, so the suite needs somebody who has it and
                    // somebody who does not.
                    'warden@example.test' => ['password' => 'x', 'roles' => ['ROLE_ADMIN', 'ROLE_USER']],
                ]]],
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

        // The OWNING MODULE of the hub's files, played by a fixture, and tagged by
        // hand for the same reason the voter above is.
        $container->services()
            ->set(StubFileSource::class)
            ->tag(FileSourceInterface::TAG);

        /*
         * The HOST's widget framework, played by the doubles in
         * tests/Fixtures/Uhifadhi/Service. They are registered under their own
         * class names because that is how the host registers them and how this
         * bundle's controller asks for them; see the note at the top of each.
         */
        $container->services()
            ->set(WidgetService::class)
            ->set(WidgetEndpoint::class)
            ->args([
                service(WidgetService::class),
                service('security.token_storage'),
                service('security.csrf.token_manager'),
            ]);

        // The host provides layout.html.twig and widgets/_library.html.twig; the
        // suite stubs both. See tests/Integration/Fixtures/templates.
        $container->extension('twig', [
            'paths' => [__DIR__.'/Fixtures/templates' => null],
            'strict_variables' => true,
        ]);

        // Public aliases so the tests can reach the bundle's private services. The
        // routes reference them too, but a test needs a handle of its own.
        $container->services()
            ->alias('test_public.'.EvidenceStorage::class, 'storage.evidence_storage')
            ->public();
        $container->services()
            ->alias('test_public.'.FileRegistry::class, 'storage.file_registry')
            ->public();
        $container->services()
            ->alias('test_public.'.StubFileSource::class, StubFileSource::class)
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
