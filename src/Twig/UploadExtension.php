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

namespace Uhifadhi\Storage\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * THE ONE TWIG LINE A MODULE WRITES.
 *
 *     {{ render_upload('incident:' ~ incident.uuid, 'tile') }}
 *
 * Declares the function; {@see UploadRuntime} builds it. The split is not
 * decoration — Twig constructs every EXTENSION as soon as the `twig` service is
 * built, and an image build does exactly that (asset compilation fires an event
 * and UX Icons warms its cache off it). This one would drag the target registry,
 * the router and a CSRF manager into a build stage that has no request. A
 * runtime is constructed lazily, on the first call, which is a render, which is
 * a request. The core's `ShellExtension` states the same reasoning.
 */
final class UploadExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            // The whole component: markup, behaviour, endpoint and token, from
            // one call. `is_safe: html` because what comes back IS a rendered
            // template and escaping it would print the markup.
            new TwigFunction(
                'render_upload',
                [UploadRuntime::class, 'render'],
                ['is_safe' => ['html']],
            ),
        ];
    }
}
