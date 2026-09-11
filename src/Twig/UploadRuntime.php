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

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;
use Uhifadhi\Storage\Service\UploadService;
use Uhifadhi\Storage\Upload\UploadDom;

/**
 * WHAT `render_upload()` NEEDS TO KNOW, GATHERED IN ONE PLACE.
 *
 * The module writing the Twig line knows a target string and which of the two
 * presentations it wants. Everything else — the endpoint, the removal URL, the
 * token, the allowed kinds, the size cap, the queue cap — is the storage's, and
 * a module that had to pass any of it would be a module that could get it wrong.
 *
 * A TARGET THAT DOES NOT RESOLVE DRAWS NOTHING. Not a broken box and not an
 * error: a template naming a record that is not there, or a kind no installed
 * module claims, is a page that should simply not offer an upload. The endpoint
 * would refuse it anyway, and offering a door that cannot open is worse than no
 * door.
 */
final readonly class UploadRuntime implements RuntimeExtensionInterface
{
    /** The design's two presentations, and the only two strings accepted. */
    public const string ZONE = 'zone';
    public const string TILE = 'tile';

    public function __construct(
        private Environment $twig,
        private UploadService $uploads,
        private UrlGeneratorInterface $urls,
        private CsrfTokenManagerInterface $csrf,
    ) {
    }

    /**
     * @param string                $target       "<kind>:<recordId>", or a kind alone where the
     *                                            target has no record behind it
     * @param string                $presentation {@see ZONE} — receiving the file IS the step —
     *                                            or {@see TILE}, one cell of a grid of things
     *                                            already attached
     * @param array<string, scalar> $attrs        extra attributes for the component's own
     *                                            element: a class, an id, a label. Escaped.
     */
    public function render(string $target, string $presentation = self::ZONE, array $attrs = []): string
    {
        $constraints = $this->uploads->constraintsFor($target);
        if (null === $constraints) {
            return '';
        }

        return $this->twig->render('@UhifadhiStorage/upload/_component.html.twig', [
            'target' => $target,
            'presentation' => self::TILE === $presentation ? self::TILE : self::ZONE,
            'constraints' => $constraints,
            'attrs' => $attrs,
            'uploadUrl' => $this->urls->generate('storage_upload'),
            // The key is patched in by the browser. Generated with a
            // placeholder rather than concatenated in JS, so the component never
            // assembles a platform route out of string parts and a host that
            // mounts the routes under a prefix keeps working.
            'removeUrl' => $this->urls->generate('storage_upload_remove', ['key' => UploadDom::KEY_PLACEHOLDER]),
            // Minted here rather than with Twig's csrf_token(): that function
            // only exists where the twig-bridge CSRF extension is wired, and a
            // module's screen must not depend on it.
            'token' => $this->csrf->getToken(UploadService::CSRF_TOKEN_ID)->getValue(),
        ]);
    }
}
