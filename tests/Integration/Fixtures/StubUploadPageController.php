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

namespace Uhifadhi\Storage\Tests\Integration\Fixtures;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * THE STAND-IN MODULE'S ONE SCREEN.
 *
 * A module gains uploads by writing one Twig line, so the suite needs a page
 * that writes one — otherwise every assertion about the component would be an
 * assertion about a string this suite composed rather than about what a host
 * actually renders. It is also where the CSRF token comes from: the token the
 * endpoint tests send is the token the COMPONENT minted, which is the only way
 * to prove the two halves agree.
 *
 * Extends nothing and takes Twig explicitly, the same shape the bundle's own
 * controllers use.
 */
final class StubUploadPageController
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function __invoke(string $target): Response
    {
        return new Response($this->twig->render('@StubHost/upload_page.html.twig', ['target' => $target]));
    }
}
