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

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use League\FlysystemBundle\FlysystemBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Uhifadhi\Shell\UhifadhiShellBundle;
use Uhifadhi\Storage\Registry\FileRegistry;
use Uhifadhi\Storage\Registry\FileSourceInterface;
use Uhifadhi\Storage\Service\EvidenceStorage;
use Uhifadhi\Storage\Tests\Integration\Fixtures\StubEvidenceVoter;
use Uhifadhi\Storage\Tests\Integration\Fixtures\StubFileSource;
use Uhifadhi\Storage\UhifadhiStorageBundle;
use Uhifadhi\Team\Entity\User;
use Uhifadhi\Team\UhifadhiTeamBundle;
use Uhifadhi\Widget\UhifadhiWidgetBundle;

/**
 * The smallest installation this bundle can live in: framework + twig +
 * doctrine + security + flysystem, the shell the four Files screens render
 * through and the widget framework the hub IS, talking to a REAL database
 * (STORAGE_TEST_DATABASE_URL, see phpunit.dist.xml).
 *
 * IT HAS A DATABASE NOW, AND THE CHARTER DID NOT CHANGE. Storage still owns no
 * entities — the photo records stay in the modules that own them, and nothing
 * under src/ maps a table. What arrived with uhifadhi/widget-module is a
 * database this bundle does not write to and cannot see: the hub is a widget
 * dashboard, one person's arrangement of a dashboard is a stored row, and the
 * bundle that owns that row owns its schema. The suite needs a real one because
 * doubling it is exactly what this fleet-join removed.
 *
 * IT INSTALLS uhifadhi/team-module FOR THE ACCOUNT CLASS. Widget points every
 * stored layout at `Uhifadhi\ModuleContracts\Entity\UserInterface` and cannot
 * build a schema until an installation resolves it. Resolving it to a REAL
 * account class rather than to a stub means the suite proves what an
 * installation actually does — team states the resolution from its own bundle,
 * so there is no `resolve_target_entities` block here and its absence is the
 * assertion.
 *
 * IT INSTALLS TEAM FOR THE ACCOUNT CLASS AND NOTHING ELSE. Team is also a
 * module with dashboards, and its two surfaces would land in the widget
 * registry beside this module's own; {@see OnlyThisModulesSurfacesPass} keeps
 * them out, so what this suite asserts about the registry stays about STORAGE
 * rather than about a dependency's release notes.
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
        // Twig, because the Files hub is four screens. The bundle registers them
        // only where TwigBundle and SecurityBundle are both present, and this
        // kernel is what "both present" looks like.
        yield new TwigBundle();
        yield new StimulusBundle();
        yield new UXIconsBundle();
        yield new DoctrineBundle();
        yield new SecurityBundle();
        yield new UhifadhiShellBundle();
        // Hard-required: the hub is a widget surface, not a page with widgets on it.
        yield new UhifadhiWidgetBundle();
        // For the account class every stored layout is keyed by, and nothing else.
        yield new UhifadhiTeamBundle();
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
            // The file page's removal form carries a token, and so does every
            // write to a layout, so there has to be a real manager minting them.
            'csrf_protection' => ['enabled' => true],
            // asset() has to exist: the shell's document and this module's base
            // template both link stylesheets with it. AssetMapper takes over path
            // resolution here, exactly as it does in a real installation.
            'assets' => true,
            'asset_mapper' => [
                'paths' => [__DIR__.'/Fixtures/app/assets' => ''],
            ],
        ]);

        // A minimal but REAL security setup. The people are team's own entity
        // rather than InMemoryUser, because a stored widget layout carries a
        // foreign key to a person and an in-memory one has no row to point at.
        $container->extension('security', [
            'password_hashers' => [
                'Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface' => [
                    // Test-only cost floor, the documented Symfony practice.
                    'algorithm' => 'auto',
                    'cost' => 4,
                    'time_cost' => 3,
                    'memory_cost' => 10,
                ],
            ],
            'providers' => [
                'team_user_provider' => [
                    'entity' => ['class' => User::class, 'property' => 'email'],
                ],
            ],
            'firewalls' => [
                'main' => ['lazy' => true, 'provider' => 'team_user_provider'],
            ],
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_AREAS', 'ROLE_MODULES', 'ROLE_TEAM'],
                'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'],
            ],
        ]);

        $container->extension('doctrine', [
            'dbal' => ['url' => '%env(STORAGE_TEST_DATABASE_URL)%'],
            'orm' => [
                // The skeleton's own choice, mirrored so the SQL these bundles
                // emit is exercised against the column names it will meet.
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
                // NO resolve_target_entities HERE, DELIBERATELY — team prepends
                // it. If that ever stopped happening the schema would not build
                // and this whole suite would say so at once.
            ],
        ]);

        $container->extension('storage', [
            'evidence' => [
                'adapter' => 'local',
                'directory' => self::evidenceDirectory(),
            ],
        ]);

        $container->extension('ux_icons', [
            'icon_dir' => __DIR__.'/Fixtures/icons',
            'ignore_not_found' => true,
        ]);

        // The OWNING MODULE's voter, played by a fixture. Tagged by hand — a
        // reusable-bundle test kernel does not autoconfigure, exactly as the
        // real patrol/incident bundles tag their own.
        $container->services()
            ->set(StubEvidenceVoter::class)
            ->tag('uhifadhi.evidence_access_voter');

        // The OWNING MODULE of the hub's files, played by a fixture, and tagged by
        // hand for the same reason the voter above is.
        $container->services()
            ->set(StubFileSource::class)
            ->tag(FileSourceInterface::TAG);

        // Public aliases so the tests can reach private services. The routes
        // reference them too, but a test needs a handle of its own.
        foreach ([
            EvidenceStorage::class => 'storage.evidence_storage',
            FileRegistry::class => 'storage.file_registry',
            StubFileSource::class => StubFileSource::class,
            \Uhifadhi\Widget\Registry\WidgetSurfaceRegistry::class => 'widget.surfaces',
            \Uhifadhi\Widget\Service\WidgetService::class => 'widget.service',
            \Uhifadhi\Widget\Service\WidgetEndpoint::class => 'widget.endpoint',
            \Uhifadhi\Team\Repository\UserRepository::class => \Uhifadhi\Team\Repository\UserRepository::class,
        ] as $class => $serviceId) {
            $container->services()->alias('test_public.'.$class, $serviceId)->public();
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // Mounted exactly as the recipe's config/routes/storage.yaml mounts it:
        // the bundle's controllers carry their own #[Route], so the directory is
        // imported.
        $routes->import('@UhifadhiStorageBundle/src/Controller/', 'attribute');

        // The front door every installation has — the crumb on every Files
        // screen points at it, and a link to nowhere is a broken page.
        $routes->import('@UhifadhiShellBundle/src/Controller/', 'attribute');
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Team is booted for its account class, not for its dashboards.
        $container->addCompilerPass(new OnlyThisModulesSurfacesPass());
    }

    /**
     * THE STAND-IN INSTALLATION'S PROJECT DIRECTORY — an application's asset
     * side and nothing else. The shell's document renders the importmap of
     * whatever application it is installed in, so a suite that renders any page
     * through the page frame needs an application that has one. Pointing the
     * kernel at a fixture is how it gets one without this bundle growing an
     * importmap of its own, which a shipped bundle has no business carrying.
     */
    public function getProjectDir(): string
    {
        return __DIR__.'/Fixtures/app';
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
