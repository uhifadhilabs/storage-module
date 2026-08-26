<?php

declare(strict_types=1);

/*
 * This file is part of uhifadhi.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Model\WidgetCatalog;

/**
 * THE HOST'S SERVICE, DOUBLED — signatures pinned, behaviour minimal.
 *
 * See the note on WidgetService beside this file for why these two are doubles
 * rather than byte-for-byte copies. The status codes below are the host's own
 * contract and are pinned deliberately, because a bundle controller returns them
 * untouched: 204 on success, 422 on an unreadable layout, 404 on an unknown
 * preset, 403 on a bad token.
 *
 * The host's own docblock explains why it is a SERVICE and not a trait or a base
 * controller, and that reason is exactly why this bundle can use it: module
 * bundles register plain controller classes with no base class.
 */
final readonly class WidgetEndpoint
{
    public function __construct(
        private WidgetService $widgets,
        private TokenStorageInterface $tokenStorage,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public static function csrfTokenId(string $surface, ?Uuid $areaUuid = null): string
    {
        return 'widgets_'.$surface.(null !== $areaUuid ? '_'.$areaUuid->toRfc4122() : '');
    }

    public function csrfToken(WidgetCatalog $catalog, ?Uuid $areaUuid = null): string
    {
        return $this->csrfTokenManager->getToken(self::csrfTokenId($catalog->surface, $areaUuid))->getValue();
    }

    public function save(Request $request, WidgetCatalog $catalog, ?Uuid $areaUuid = null): Response
    {
        if (!$this->valid($request, $catalog, $areaUuid)) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($request->getContent(), true, flags: \JSON_THROW_ON_ERROR);
            $this->widgets->save($catalog, $this->userId(), $payload, $areaUuid);
        } catch (\JsonException|\InvalidArgumentException) {
            return new Response('', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function applyPreset(Request $request, WidgetCatalog $catalog, string $presetId, ?Uuid $areaUuid = null): Response
    {
        if (!$this->valid($request, $catalog, $areaUuid)) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        try {
            $this->widgets->applyPreset($catalog, $this->userId(), $areaUuid, $presetId);
        } catch (\InvalidArgumentException) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function copyPreset(Request $request, WidgetCatalog $catalog, string $presetId, ?Uuid $areaUuid = null): Response
    {
        return $this->applyPreset($request, $catalog, $presetId, $areaUuid);
    }

    public function createCustomPreset(Request $request, WidgetCatalog $catalog, ?Uuid $areaUuid = null): Response
    {
        return $this->valid($request, $catalog, $areaUuid)
            ? new Response('', Response::HTTP_NO_CONTENT)
            : new Response('', Response::HTTP_FORBIDDEN);
    }

    public function applyCustomPreset(Request $request, WidgetCatalog $catalog, Uuid $presetUuid, ?Uuid $areaUuid = null): Response
    {
        return $this->valid($request, $catalog, $areaUuid)
            ? new Response('', Response::HTTP_NOT_FOUND)
            : new Response('', Response::HTTP_FORBIDDEN);
    }

    public function renameCustomPreset(Request $request, WidgetCatalog $catalog, Uuid $presetUuid, ?Uuid $areaUuid = null): Response
    {
        return $this->applyCustomPreset($request, $catalog, $presetUuid, $areaUuid);
    }

    public function deleteCustomPreset(Request $request, WidgetCatalog $catalog, Uuid $presetUuid, ?Uuid $areaUuid = null): Response
    {
        return $this->applyCustomPreset($request, $catalog, $presetUuid, $areaUuid);
    }

    public function reset(Request $request, WidgetCatalog $catalog, ?Uuid $areaUuid = null): Response
    {
        if (!$this->valid($request, $catalog, $areaUuid)) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }
        $this->widgets->reset($catalog, $this->userId(), $areaUuid);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function userId(): int
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (null === $user) {
            throw new \LogicException('No signed-in user.');
        }

        return abs(crc32($user->getUserIdentifier()));
    }

    private function valid(Request $request, WidgetCatalog $catalog, ?Uuid $areaUuid): bool
    {
        $token = $request->headers->get('X-CSRF-Token') ?? '';

        return $this->csrfTokenManager->isTokenValid(new CsrfToken(self::csrfTokenId($catalog->surface, $areaUuid), $token));
    }
}
