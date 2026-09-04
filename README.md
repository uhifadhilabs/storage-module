# uhifadhi/storage-module

The uhifadhi platform's file-storage machinery: named Flysystem storages, a
private evidence API with a detected-MIME allowlist, thumbnails, and one
authenticated route by which any of it comes back out.

## What it is

Mechanism only. The bundle owns no entities, no migrations and no screens of a
module's own: it holds the named storages, the store/stream/delete API, the MIME
allowlist and size cap, thumbnail generation, and the authenticated serving
route. Which record a file hangs off, and who may read it, stays with the module
that wrote the key — see [the charter](docs/charter.md).

On top of that machinery it ships one optional cross-module screen, the **Files
hub** (`/files`), which is a dashboard on `uhifadhi/widget-module` — which is why
that package is a hard requirement rather than a suggestion.

## Installation

```console
composer require uhifadhi/storage-module
```

The recipe registers the bundles and writes `config/packages/storage.yaml` and
`config/routes/storage.yaml`. Without Flex, `config/bundles.php` needs three
lines:

```php
League\FlysystemBundle\FlysystemBundle::class => ['all' => true],
Uhifadhi\Widget\UhifadhiWidgetBundle::class => ['all' => true],
Uhifadhi\Storage\UhifadhiStorageBundle::class => ['all' => true],
```

The bundle **prepends** its own `flysystem` block, so an installation never
writes `config/packages/flysystem.yaml` to get an evidence store.

The widget module keeps its layouts in two tables of its own, so after
installing run your own `doctrine:migrations:diff` and `migrate` — and see that
module's recipe for the single `resolve_target_entities` line an installation
without `uhifadhi/team-module` has to write.

## Getting started

An unconfigured installation already has a working, private, on-disk evidence store.
Three steps put files through it:

**1 · Store and read bytes** from the module that owns the record:

```php
use Uhifadhi\Storage\Service\EvidenceStorage;

$stored = $evidence->store($uploadedFile, 'observation/'.$uuid, $clientUuid);

$stored->key;        // RELATIVE, always — this is what you record on your entity
$stored->mimeType;   // DETECTED, never what the client claimed
$stored->thumbKey;   // the ~400px variant, or NULL
```

**2 · Mount the routes**, so stored bytes can come back out:

```yaml
# config/routes/storage.yaml
storage:
    resource: '@UhifadhiStorageBundle/src/Controller/'
    type: attribute
```

The serving route `storage_evidence_show` (`GET /storage/evidence/{key}`) is
registered only when SecurityBundle is in the kernel.

**3 · Ship a voter** for your key prefix, tagged
`uhifadhi.evidence_access_voter`. Storage cannot know what an observation is, so
it asks the module that wrote the key — and **denies by default** until a voter
claims it and agrees.

Configuration (a different adapter, a narrower allowlist, the Files hub) is
optional and layered on from there.

## Learn more

- [docs/charter.md](docs/charter.md) — what belongs in this bundle and what stays in the owning module.
- [docs/configuration.md](docs/configuration.md) — the full `storage.yaml` reference: local and S3-compatible object storage, and why visibility is not a setting.
- [docs/evidence-api.md](docs/evidence-api.md) — `EvidenceStorage`, the key rules, the three exceptions, validation and thumbnails.
- [docs/serving-and-permissions.md](docs/serving-and-permissions.md) — the serving route and the permission seam: voters, deny-by-default, and enumeration.
- [docs/files-hub.md](docs/files-hub.md) — the cross-module `/files` screens: what an installation wires, the widgets, `FileSourceInterface`, and removal.
- [docs/adopting-in-a-module.md](docs/adopting-in-a-module.md) — step-by-step adoption for patrol-module and incident-module.
- [docs/service-reference.md](docs/service-reference.md) — service ids, classes, the tag and the route.
- [docs/development.md](docs/development.md) — running the suite, and what CI deliberately does without.

## License

**AGPL-3.0-or-later** — see [LICENSE](LICENSE): the same license as the
uhifadhi platform this module is part of. Use, modify and self-host freely; if you
offer a modified version to users over a network, they are entitled to the
source of what they're running.
