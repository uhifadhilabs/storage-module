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

use Symfony\Component\Uid\Uuid;
use Uhifadhi\Model\WidgetCatalog;
use Uhifadhi\Model\WidgetPreset;

/**
 * THE HOST'S SERVICE, DOUBLED — signatures pinned, persistence in memory.
 *
 * The host's real WidgetService stores a person's layout in two Doctrine tables.
 * This bundle owns no entities and its suite has no database (docs/charter.md),
 * so copying that class byte-for-byte the way the value objects beside it are
 * copied would mean dragging Doctrine and a Postgres service into a bundle whose
 * whole charter is that it has neither.
 *
 * So what is pinned here is THE PART THIS BUNDLE ACTUALLY DEPENDS ON: the public
 * signatures. Every method below has the host's exact name, parameters and return
 * type; the bodies are the smallest thing that behaves correctly for one request.
 * If the host changes a signature, this file stops compiling and the bundle's
 * suite says so — which is the only kind of drift a stub can catch and the only
 * kind that would break the bundle.
 *
 * @see https://github.com/uhifadhilabs — the host's src/Service/WidgetService.php
 */
final class WidgetService
{
    public const int NAME_MAX = 60;

    /** @var array<string, array{order: list<string>, widgets: array<string, array{on: bool, cols: int}>}> */
    private array $stored = [];

    /** @var array<string, array{kind: string, id: string}> */
    private array $active = [];

    /**
     * @return list<array{id: string, label: string, group: string, on: bool, cols: int, spans: list<int>}>
     */
    public function resolve(WidgetCatalog $catalog, ?int $userId, ?Uuid $areaUuid = null): array
    {
        return self::merge($catalog, null === $userId ? null : ($this->stored[$this->row($catalog, $userId, $areaUuid)] ?? null));
    }

    /**
     * @return array{kind: string, id: string, label: string}
     */
    public function activeRef(WidgetCatalog $catalog, ?int $userId, ?Uuid $areaUuid = null): array
    {
        $active = null === $userId ? null : ($this->active[$this->row($catalog, $userId, $areaUuid)] ?? null);
        $id = $active['id'] ?? $catalog->defaultPresetId();
        $preset = $catalog->preset($id);

        return [
            'kind' => $active['kind'] ?? 'design',
            'id' => $id,
            'label' => null !== $preset ? $preset->label : 'Default layout',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(WidgetCatalog $catalog, int $userId, array $payload, ?Uuid $areaUuid = null): void
    {
        $this->stored[$this->row($catalog, $userId, $areaUuid)] = self::validate($catalog, $payload);
        $this->active[$this->row($catalog, $userId, $areaUuid)] = ['kind' => 'mine', 'id' => 'mine'];
    }

    public function applyPreset(WidgetCatalog $catalog, int $userId, ?Uuid $areaUuid, string $presetId): void
    {
        $preset = self::presetOf($catalog, $presetId);
        $this->stored[$this->row($catalog, $userId, $areaUuid)] = self::validate($catalog, self::presetPayload($catalog, $preset));
        $this->active[$this->row($catalog, $userId, $areaUuid)] = ['kind' => 'design', 'id' => $preset->id];
    }

    /**
     * @return list<WidgetPreset>
     */
    public function customPresets(WidgetCatalog $catalog, ?int $userId, ?Uuid $areaUuid = null): array
    {
        return [];
    }

    public function reset(WidgetCatalog $catalog, int $userId, ?Uuid $areaUuid = null): void
    {
        unset($this->stored[$this->row($catalog, $userId, $areaUuid)], $this->active[$this->row($catalog, $userId, $areaUuid)]);
    }

    /**
     * Reading stored preferences NEVER throws: a row written by an older release
     * must narrow a dashboard, never take it down.
     *
     * @param array{order?: list<string>, widgets?: array<string, array{on?: bool, cols?: int}>}|null $stored
     *
     * @return list<array{id: string, label: string, group: string, on: bool, cols: int, spans: list<int>}>
     */
    public static function merge(WidgetCatalog $catalog, ?array $stored): array
    {
        $order = [];
        foreach ($stored['order'] ?? [] as $id) {
            if ($catalog->has($id) && !\in_array($id, $order, true)) {
                $order[] = $id;
            }
        }
        foreach ($catalog->ids() as $id) {
            if (!\in_array($id, $order, true)) {
                $order[] = $id;
            }
        }

        $resolved = [];
        foreach ($order as $id) {
            $definition = $catalog->get($id);
            $entry = $stored['widgets'][$id] ?? [];
            $resolved[] = [
                'id' => $id,
                'label' => $definition->label,
                'group' => $definition->group,
                'on' => \array_key_exists('on', $entry) ? (bool) $entry['on'] : $definition->on,
                'cols' => $catalog->clamp($id, isset($entry['cols']) ? (int) $entry['cols'] : $definition->cols),
                'spans' => $definition->spans,
            ];
        }

        return $resolved;
    }

    /**
     * Writing ALWAYS throws on an unknown id: a client posting nonsense is a bug
     * somewhere, and swallowing it would hide the bug and lose the layout.
     *
     * @param array<string, mixed> $payload
     *
     * @return array{order: list<string>, widgets: array<string, array{on: bool, cols: int}>}
     */
    public static function validate(WidgetCatalog $catalog, array $payload): array
    {
        $order = [];
        /** @var mixed $rawOrder */
        $rawOrder = $payload['order'] ?? [];
        foreach (\is_array($rawOrder) ? $rawOrder : [] as $id) {
            if (!\is_string($id) || !$catalog->has($id)) {
                throw new \InvalidArgumentException('Unknown widget in the posted order.');
            }
            if (!\in_array($id, $order, true)) {
                $order[] = $id;
            }
        }
        foreach ($catalog->ids() as $id) {
            if (!\in_array($id, $order, true)) {
                $order[] = $id;
            }
        }

        /** @var mixed $rawWidgets */
        $rawWidgets = $payload['widgets'] ?? [];
        $rawWidgets = \is_array($rawWidgets) ? $rawWidgets : [];

        $widgets = [];
        foreach ($order as $id) {
            $definition = $catalog->get($id);
            /** @var mixed $entry */
            $entry = $rawWidgets[$id] ?? [];
            $entry = \is_array($entry) ? $entry : [];
            /** @var mixed $cols */
            $cols = $entry['cols'] ?? null;
            $widgets[$id] = [
                'on' => \array_key_exists('on', $entry) ? (bool) $entry['on'] : $definition->on,
                'cols' => $catalog->clamp($id, is_numeric($cols) ? (int) $cols : $definition->cols),
            ];
        }

        return ['order' => $order, 'widgets' => $widgets];
    }

    public static function presetOf(WidgetCatalog $catalog, string $presetId): WidgetPreset
    {
        $preset = $catalog->preset($presetId);
        if (null === $preset) {
            throw new \InvalidArgumentException(\sprintf('No preset "%s" on this surface.', $presetId));
        }

        return $preset;
    }

    /**
     * @return array{order: list<string>, widgets: array<string, array{on: bool, cols: int}>}
     */
    public static function presetPayload(WidgetCatalog $catalog, WidgetPreset $preset): array
    {
        $order = $preset->ids();
        foreach ($catalog->ids() as $id) {
            if (!\in_array($id, $order, true)) {
                $order[] = $id;
            }
        }

        $widgets = [];
        foreach ($order as $id) {
            $widgets[$id] = $preset->shows($id)
                ? ['on' => true, 'cols' => $preset->cols($id)]
                : ['on' => false, 'cols' => $catalog->get($id)->cols];
        }

        return ['order' => $order, 'widgets' => $widgets];
    }

    private function row(WidgetCatalog $catalog, int $userId, ?Uuid $areaUuid): string
    {
        return $catalog->surface.'/'.$userId.'/'.($areaUuid?->toRfc4122() ?? '-');
    }
}