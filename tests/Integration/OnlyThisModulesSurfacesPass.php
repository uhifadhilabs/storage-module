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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Uhifadhi\Storage\Widget\FilesWidgets;
use Uhifadhi\Widget\Registry\WidgetSurfaceInterface;

/**
 * THE SUITE DECLARES THIS MODULE'S SURFACES AND NOBODY ELSE'S.
 *
 * {@see TestKernel} boots uhifadhi/team-module for one reason: an account class
 * the stored layouts can be resolved against. Team also happens to be a module
 * with dashboards of its own, so it tags two surfaces into the widget registry —
 * and every one it adds or renames would otherwise rewrite the expected value of
 * a test about THIS bundle. That is a dependency's release notes deciding
 * whether this suite is green.
 *
 * So the tag is cleared off everything outside this module's own namespace. The
 * rule is by namespace, not by service id: a surface team ships tomorrow is
 * excluded for the same reason as the two it ships today, and the assertion that
 * the registry holds the Files hub stays an assertion about storage rather than
 * about a version number.
 *
 * Copied in discipline from uhifadhi/widget-module's own suite, which needs the
 * same isolation for the same reason and names it OnlyThisSuitesSurfacesPass.
 */
final class OnlyThisModulesSurfacesPass implements CompilerPassInterface
{
    /** Everything this module declares lives beside {@see FilesWidgets}. */
    private const string OURS = 'Uhifadhi\\Storage\\Widget\\';

    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds(WidgetSurfaceInterface::TAG)) as $id) {
            $definition = $container->getDefinition($id);
            $class = $definition->getClass();
            $resolved = null === $class ? null : $container->getParameterBag()->resolveValue($class);

            if (!\is_string($resolved) || !str_starts_with($resolved, self::OURS)) {
                $definition->clearTag(WidgetSurfaceInterface::TAG);
            }
        }
    }
}
