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

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Uhifadhi\Storage\Exception\UploadRefusedException;
use Uhifadhi\Storage\Service\UploadService;
use Uhifadhi\Storage\Upload\UploadDom;

/**
 * THE ONE ENDPOINT EVERY UPLOAD IN THE PRODUCT GOES THROUGH.
 *
 * Two calls, exactly as the component sheet states them:
 *
 *     POST   /files/upload   multipart: target, file
 *     DELETE /files/{key}
 *
 * LEAN ON PURPOSE. It reads the request, checks the token, hands two strings and
 * a file to {@see UploadService}, and turns whatever comes back into JSON. Every
 * decision — which module, which record, who may, what kinds, what the file
 * became — is the service's, and through it the owning module's.
 *
 * No AbstractController: a reusable bundle's controller must not depend on the
 * host's service-subscriber container, so its collaborators are constructor
 * arguments and it is registered explicitly. Patterned on FrameworkBundle's own
 * {@see \Symfony\Bundle\FrameworkBundle\Controller\TemplateController}, which
 * extends nothing and is registered `->args([...])->public()` with no tag.
 *
 * @see https://symfony.com/doc/current/bundles/best_practices.html
 * @see vendor/symfony/framework-bundle/Controller/TemplateController.php
 *
 * REGISTERED ONLY WHERE SecurityBundle IS IN THE KERNEL — see
 * {@see \Uhifadhi\Storage\UhifadhiStorageBundle::loadExtension()}. A write
 * endpoint that cannot tell a signed-in ranger from a stranger must not exist at
 * all, rather than exist and guess.
 *
 * MULTIPART, READ THE FRAMEWORK'S WAY. The file comes off `$request->files`,
 * which is Symfony's own normalisation of PHP's `$_FILES`; the guard on
 * `UploadedFile::isValid()` is in the service, beside the other refusals, so the
 * sentence a person reads is written in one place.
 * @see https://symfony.com/doc/current/controller/upload_file.html
 * @see vendor/symfony/http-foundation/File/UploadedFile.php
 *
 * THE TOKEN TRAVELS IN A HEADER, with the conventional `_token` field as a
 * fallback — the same double door the core's widget endpoint opens
 * (`vendor/uhifadhi/uhifadhi/src/Uhifadhi/Bundle/ShellBundle/Widget/Service/WidgetEndpoint.php`,
 * `denyUnlessCsrfValid()`), because a `DELETE` has no body to put a field in and
 * a multipart `POST` does.
 * @see https://symfony.com/doc/current/security/csrf.html
 */
final class UploadController
{
    public function __construct(
        private readonly UploadService $uploads,
        private readonly CsrfTokenManagerInterface $csrf,
        private readonly TokenStorageInterface $tokens,
    ) {
    }

    #[Route('/files/upload', name: 'storage_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        if (!$this->tokenIsValid($request)) {
            return self::refusal('That upload did not carry a valid token.', Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('file');

        try {
            return new JsonResponse($this->uploads->receive(
                $request->request->getString('target'),
                $file instanceof UploadedFile ? $file : null,
                $this->currentUser(),
            ));
        } catch (UploadRefusedException $refused) {
            return self::refusal($refused->getMessage(), $refused->statusCode);
        }
    }

    /**
     * The `.+` requirement is load-bearing: a key is a PATH
     * ("incident/0199a/ef12.jpg"), and the default placeholder pattern stops at
     * the first slash, which would make every real key a 404.
     */
    #[Route('/files/{key}', name: 'storage_upload_remove', requirements: ['key' => '.+'], methods: ['DELETE'])]
    public function remove(Request $request, string $key): JsonResponse
    {
        if (!$this->tokenIsValid($request)) {
            return self::refusal('That removal did not carry a valid token.', Response::HTTP_FORBIDDEN);
        }

        try {
            $this->uploads->remove($key, $this->currentUser());
        } catch (UploadRefusedException $refused) {
            return self::refusal($refused->getMessage(), $refused->statusCode);
        }

        return new JsonResponse(['key' => $key]);
    }

    /**
     * THE SHAPE OF EVERY REFUSAL: one key, one sentence. The component prints
     * what it is given and has no error vocabulary of its own, so anything the
     * endpoint cannot say in a sentence it cannot say at all.
     */
    private static function refusal(string $sentence, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $sentence], $status);
    }

    private function tokenIsValid(Request $request): bool
    {
        $submitted = $request->headers->get(UploadDom::CSRF_HEADER)
            ?? $request->request->getString('_token');

        return $this->csrf->isTokenValid(new CsrfToken(UploadService::CSRF_TOKEN_ID, $submitted));
    }

    private function currentUser(): ?UserInterface
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof UserInterface ? $user : null;
    }
}
