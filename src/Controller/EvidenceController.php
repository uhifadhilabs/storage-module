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

namespace Uhifadhi\Storage\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Uhifadhi\Storage\Exception\EvidenceNotFoundException;
use Uhifadhi\Storage\Exception\InvalidEvidenceKeyException;
use Uhifadhi\Storage\Security\EvidenceAccessDecider;
use Uhifadhi\Storage\Service\EvidenceKey;
use Uhifadhi\Storage\Service\EvidenceStorage;

/**
 * The ONE way stored evidence comes back out.
 *
 * There is no public URL and no document-root path anywhere in this bundle, so
 * every read passes through here and therefore through the owning module's
 * voter. That is the point of the design: authorization cannot be bypassed by
 * knowing a filename, because knowing the filename is not enough.
 *
 * No AbstractController: a reusable bundle's controller must not depend on the
 * host's service-subscriber container, so its collaborators are constructor
 * arguments and it is registered explicitly (see config/services.php).
 *
 * Registered ONLY when SecurityBundle is in the kernel — see
 * UhifadhiStorageBundle::loadExtension() for why a host without security
 * gets no route at all rather than an unprotected one.
 */
final class EvidenceController
{
    public function __construct(
        private readonly EvidenceStorage $storage,
        private readonly EvidenceAccessDecider $decider,
        private readonly TokenStorageInterface $tokens,
    ) {
    }

    /**
     * The `.+` requirement is load-bearing: a key is a PATH
     * ("observation/0199a/ef12.jpg"), and the default placeholder pattern stops
     * at the first slash, which would make every real key a 404.
     */
    #[Route(
        path: '/storage/evidence/{key}',
        name: 'storage_evidence_show',
        requirements: ['key' => '.+'],
        methods: ['GET'],
    )]
    public function __invoke(string $key): Response
    {
        // A malformed key is refused as forbidden, not as not-found: it is an
        // attempt at something, and it should learn nothing about what exists.
        if (!EvidenceKey::isValid($key)) {
            throw new AccessDeniedHttpException('That is not a readable evidence key.');
        }

        // PERMISSION BEFORE EXISTENCE, deliberately. Checking the other way
        // round would turn 404-vs-403 into an oracle for enumerating which
        // observations have photographs attached.
        if (!$this->decider->mayRead($key, $this->currentUser())) {
            throw new AccessDeniedHttpException('You may not read that evidence.');
        }

        try {
            $resource = $this->storage->stream($key);
        } catch (EvidenceNotFoundException) {
            throw new NotFoundHttpException('There is no evidence stored under that key.');
        } catch (InvalidEvidenceKeyException) {
            throw new AccessDeniedHttpException('That is not a readable evidence key.');
        }

        $response = new StreamedResponse(static function () use ($resource): void {
            // Copy to the output stream rather than reading into a string: a
            // 12MB photograph should never sit in PHP's memory in one piece.
            $output = fopen('php://output', 'w');
            if (false !== $output) {
                stream_copy_to_stream($resource, $output);
                fclose($output);
            }
            if (\is_resource($resource)) {
                fclose($resource);
            }
        });

        $response->headers->set('Content-Type', $this->storage->mimeType($key));
        $response->headers->set('Content-Length', (string) $this->storage->byteSize($key));

        // The browser is told the type once and forbidden from re-deciding it.
        // Without this, a file whose type could not be detected and is served
        // as octet-stream could still be sniffed into something executable.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Evidence must never sit in a shared cache: "private" keeps proxies
        // out, and no-store keeps it off disk on the way past.
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0, must-revalidate');

        // The filename is GENERATED from the key's last segment, never taken
        // from whatever the uploader called the file, and inline so nothing is
        // ever offered as a download a browser might then run.
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_INLINE,
                basename($key),
                // The fallback must be plain ASCII; the key charset already is.
                preg_replace('/[^A-Za-z0-9._-]/', '_', basename($key)) ?? 'evidence',
            ),
        );

        return $response;
    }

    private function currentUser(): ?UserInterface
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof UserInterface ? $user : null;
    }
}
