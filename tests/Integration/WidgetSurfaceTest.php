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

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Uhifadhi\Storage\Widget\FilesWidgets;
use Uhifadhi\Widget\Registry\WidgetSurfaceRegistry;
use Uhifadhi\Widget\Service\WidgetEndpoint;
use Uhifadhi\Widget\Service\WidgetService;

/**
 * THE FILES HUB IS A DECLARED SURFACE OF THE REAL WIDGET FRAMEWORK.
 *
 * Before this module joined the fleet it compiled against doubles of the old
 * application's widget classes, which meant the one thing it could never prove
 * was the thing that matters: that an installation booting uhifadhi/widget-module
 * finds this module's dashboard in the registry. A surface nothing registers is
 * a surface `widget:prune` reads as an orphan — it would delete every layout
 * anybody ever saved of the Files hub, and the module would never notice.
 *
 * So the assertion is about the REGISTRY rather than about the catalogue: the
 * catalogue is a value object any unit test can build, and being findable is the
 * part that only exists once the tag is on the service.
 */
final class WidgetSurfaceTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheHubIsRegisteredAsASurfaceOfTheRealFramework(): void
    {
        self::bootKernel();
        $registry = self::getContainer()->get('test_public.'.WidgetSurfaceRegistry::class);
        self::assertInstanceOf(WidgetSurfaceRegistry::class, $registry);

        self::assertTrue(
            $registry->has(FilesWidgets::SURFACE),
            'the Files hub is not in the widget registry, so widget:prune reads its layouts as orphans',
        );
        self::assertSame(
            FilesWidgets::SURFACE,
            $registry->catalog(FilesWidgets::SURFACE)?->surface,
        );
    }

    public function testTheFrameworkItselfIsTheRealOneAndNotADouble(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertInstanceOf(WidgetService::class, $container->get('test_public.'.WidgetService::class));
        self::assertInstanceOf(WidgetEndpoint::class, $container->get('test_public.'.WidgetEndpoint::class));
    }
}
