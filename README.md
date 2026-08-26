# uhifadhilabs/storage-module

The uhifadhi platform's file-storage machinery: named Flysystem storages, a
private evidence API with a detected-MIME allowlist, thumbnails, and one
authenticated route by which any of it comes back out.

## Contents

- [Charter — what belongs here and what does not](#charter--what-belongs-here-and-what-does-not)
- [Installation](#installation)
- [Configuration](#configuration)
  - [Local (default)](#local-default)
  - [S3-compatible object storage](#s3-compatible-object-storage)
- [The evidence API](#the-evidence-api)
- [Validation](#validation)
- [Thumbnails](#thumbnails)
- [Serving, and the permission seam](#serving-and-the-permission-seam)
- [The Files hub](#the-files-hub)
  - [What a host wires](#what-a-host-wires)
  - [The sidebar line](#the-sidebar-line)
  - [The screens](#the-screens)
  - [Putting a module's files on the hub](#putting-a-modules-files-on-the-hub)
  - [Removal — remove, never delete](#removal--remove-never-delete)
  - [The design this hub is a port of](#the-design-this-hub-is-a-port-of)
- [Adopting it in a module](#adopting-it-in-a-module)
  - [patrol-module](#patrol-module)
  - [incident-module](#incident-module)
- [Service reference](#service-reference)
- [Development](#development)

## Charter — what belongs here and what does not

**This bundle is mechanism only.**

It owns **no entities**, no migrations and no screens. The per-module photo
records — `ObservationPhoto` in patrol, whatever incidents grows — stay in the
modules that own them, because only those modules know what a photograph is
attached to, who may see it, and what should happen when the parent record is
deleted.

What lives here is the part every module would otherwise re-implement, slightly
differently each time:

| In this bundle | In the owning module |
|---|---|
| The named storages (`storage.evidence`) | The entity that records a key |
| `store()` / `stream()` / `delete()` / `exists()` | Deciding *when* to call them |
| The MIME allowlist and size cap | Narrowing them, if a deployment must |
| Thumbnail generation | Displaying the thumbnail |
| The authenticated serving route | The **voter** that says who may read a key |

The last row is the important one. Storage cannot know what an observation is,
so it does not try: it asks the module that wrote the key. See
[the permission seam](#serving-and-the-permission-seam).

## Installation

```console
composer require uhifadhilabs/storage-module
```

Register both bundles in `config/bundles.php`:

```php
League\FlysystemBundle\FlysystemBundle::class => ['all' => true],
UhifadhiLabs\Storage\UhifadhiLabsStorageBundle::class => ['all' => true],
```

Nothing else is required. The bundle **prepends** its own `flysystem` block, so
a host never writes `config/packages/flysystem.yaml` to get an evidence store.

## Configuration

Everything below is optional — an unconfigured host gets a working, private,
on-disk store. Configuration lives in `config/packages/storage.yaml`.

Two things are deliberately **not** configurable: there is no `visibility` key
and no `public_url` key. The evidence storage is private by construction,
because "private unless someone remembers to say so" is how deployments end up
serving field photographs to the open internet, and a public URL would route
around the permission seam entirely.

### Local (default)

```yaml
storage:
    evidence:
        adapter: local
        directory: '%kernel.project_dir%/var/storage/evidence'   # outside the document root, always
        max_bytes: 12582912          # 12 MiB
        thumbnail_long_edge: 400
        allowed_mime_types: ['image/jpeg', 'image/png', 'image/heic', 'image/heif', 'image/webp']
```

### S3-compatible object storage

Hetzner Object Storage is the production target. It is S3-compatible, so the
`asyncaws` adapter drives it unchanged; Minio and AWS itself work the same way.

```console
composer require league/flysystem-async-aws-s3
```

```yaml
storage:
    evidence:
        adapter: s3
        s3:
            endpoint: '%env(STORAGE_S3_ENDPOINT)%'
            bucket:   '%env(STORAGE_S3_BUCKET)%'
            region:   '%env(STORAGE_S3_REGION)%'
            key:      '%env(STORAGE_S3_KEY)%'
            secret:   '%env(STORAGE_S3_SECRET)%'
            prefix:   ''              # optional, so one bucket can hold several deployments
            path_style_endpoint: true # default
```

`.env` for Hetzner (`fsn1`, `nbg1` and `hel1` are the region/location codes):

```dotenv
STORAGE_S3_ENDPOINT=https://fsn1.your-objectstorage.com
STORAGE_S3_BUCKET=uhifadhi-evidence
STORAGE_S3_REGION=fsn1
STORAGE_S3_KEY=
STORAGE_S3_SECRET=
```

Notes that cost an afternoon if missed:

- **`path_style_endpoint` must stay true.** Hetzner (and Minio) address buckets
  as `endpoint/bucket`. With it off, the client invents a `bucket.endpoint`
  hostname that does not resolve.
- **The bucket must be private.** The bundle marks every object and directory
  private, but a bucket-level public policy overrides that from outside.
- **Choosing `s3` without an `endpoint` and a `bucket` fails at compile time**,
  not on the first upload in production.
- Credentials stay as env placeholders in the compiled container, so a cached
  container is never a file full of secrets.

## The evidence API

```php
use UhifadhiLabs\Storage\Service\EvidenceStorage;

$stored = $evidence->store($uploadedFile, 'observation/'.$uuid, $clientUuid);

$stored->key;        // 'observation/0199a…/ef12….jpg'  — RELATIVE, always
$stored->mimeType;   // 'image/jpeg' — DETECTED, never what the client claimed
$stored->byteSize;   // int
$stored->thumbKey;   // 'observation/0199a…/ef12….jpg.thumb.jpg', or NULL

$resource = $evidence->stream($stored->key);   // ready for a StreamedResponse
$evidence->exists($stored->key);               // bool
$evidence->delete($stored->key);               // removes the original AND its thumbnail
```

`store()` accepts any `\SplFileInfo` — an `UploadedFile` from a request, or a
plain `File` for an importer that never touched HTTP.

**Keys are relative, in and out.** That is what lets a deployment move from a
local directory to object storage without rewriting a single stored row. A key
is a path of plain segments (`[A-Za-z0-9._-]`), and anything else — absolute,
traversing, spaced, a Windows drive — is refused at the door rather than left
to callers to remember.

Three exceptions, distinguished so callers can answer correctly:

| Exception | Meaning | Retry? |
|---|---|---|
| `EvidenceRejectedException` | The file is not acceptable (`->reason` is a `RejectionReasonEnum`) | No — it will fail again |
| `EvidenceStorageFailedException` | The store failed: full disk, transient mount, S3 timeout | Yes |
| `InvalidEvidenceKeyException` | The caller built a key that is not relative | No — it is a bug |

## Validation

`EvidenceConstraints` carries the allowlist and the size cap, and is reusable on
its own for a caller that wants to validate before committing to an upload:

```php
$constraints->validate($file);              // throws EvidenceRejectedException
$constraints->allows('image/heic');         // bool
EvidenceConstraints::extensionFor($mime);   // 'jpg' | 'png' | 'heic' | 'heif' | 'webp'
```

These are **deliberately the semantics patrol-module already applies** in
`PhotoSyncService::guardFile()` / `extensionFor()` — the same three checks in
the same order, the same five types, the same "detected type, never the
filename" rule, and the same tolerance of a file whose type cannot be detected
at all. Patrol can adopt this class and reject exactly what it rejected before.

The extension is derived from the detected type because a filename is
attacker-controlled text, and letting it choose is how an upload directory ends
up holding a `.php`.

## Thumbnails

At `store()` time, one JPEG variant is written beside the original at
`key + '.thumb.jpg'`, scaled so its long edge is at most 400px. It is never
upscaled — a photo already smaller than the target is left at its own size.

Imagick is preferred, GD is the fallback. Both are asked at runtime whether
*this build* can decode *this format*, rather than assuming, because the answer
genuinely differs per machine.

**A thumbnail that cannot be made is a `null`, never an error.** HEIC is what an
iPhone sends by default; no GD build in existence decodes it, and an ImageMagick
compiled without libheif cannot either. When that is the situation the original
is still stored and `thumbKey` is `null` — stated plainly, rather than a key
pointing at a file that is not there. Losing a ranger's photograph to a missing
image library would be an absurd trade.

Install `ext-imagick` (built with libheif) if you want HEIC previews.

## Serving, and the permission seam

```
GET /storage/evidence/{key}
```

Streams via Flysystem with the stored `Content-Type` and `Content-Length`,
`X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store, …`, and an
inline `Content-Disposition` under a filename generated from the key. There is
no public URL and no document-root path anywhere in this bundle, so **every**
read passes through this route.

The route is registered **only when SecurityBundle is in the kernel**. A host
without security gets no route at all rather than an unprotected one.

Authorization is delegated to the owning module:

```php
namespace UhifadhiLabs\Patrol\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use UhifadhiLabs\Storage\Security\EvidenceAccessVoterInterface;

final class PatrolEvidenceVoter implements EvidenceAccessVoterInterface
{
    public function claimsKey(string $key): bool
    {
        return str_starts_with($key, 'patrol/');
    }

    public function mayRead(string $key, ?UserInterface $user): bool
    {
        // Look the photo up, ask whatever the module's rules are.
    }
}
```

Tag it — a reusable bundle is not autoconfigured, so the tag is explicit:

```php
$services->set('patrol.evidence_voter', PatrolEvidenceVoter::class)
    ->args([service(ObservationPhotoRepository::class)])
    ->tag('uhifadhi.evidence_access_voter');
```

(A voter defined in the **host's** `src/` is autoconfigured and needs no tag.)

**Deny by default, in the strong sense.** A grant requires that at least one
module claimed the key *and* that every module which claimed it agreed. Silence
is a refusal, disagreement is a refusal, and a voter that throws is a refusal.
This means installing the bundle can never expose a future module's evidence in
the window before that module's voter is written.

Permission is checked **before** existence, so a 404 can never be used to
enumerate which records have photographs attached.

## The Files hub

A **cross-module screen at `/files`**: every photograph, document and track the
organisation holds, across every module and every area, in one place — each one
shown with the record it belongs to.

**A file is OWNER-BOUND.** It belongs to a record in a module: an observation's
photograph, an incident's evidence, later a permit's document. It has no life of
its own — it is created by being attached, it is seen by whoever may see its
record, it is removed only when that record allows, and it dies when the record
dies. Two consequences run through every line of this feature:

- **There is no upload control anywhere on the hub**, and that is a rule rather
  than an omission. A file arrives by being attached to a record, on that
  record's own page.
- **Every tile, row and list entry carries its owner as a link.** A file card
  with no owner tag would be a lie about the model.

The hub adds exactly the two facts no module page knows — which named place the
bytes are in, and whether the one ~400px picture was made. It knows nothing
about observations or incidents and cannot: the files on it were handed over by
the modules that own them, through [one seam](#putting-a-modules-files-on-the-hub).

### What a host wires

The screens register themselves where **SecurityBundle**, **TwigBundle** and the
host's **widget framework** (`Uhifadhi\Service\WidgetService` /
`WidgetEndpoint`) are all present, and are simply absent otherwise — a host
without one of them gets no half-working dashboard.

**1 · Mount the routes** (the same file that mounts the serving route):

```yaml
# config/routes/storage.yaml
storage:
    resource: '@UhifadhiLabsStorageBundle/src/Controller/'
    type: attribute
```

**2 · Point the settings page at the host's own administrator permission.** It
defaults to `ROLE_ADMIN` so it works out of the box; a host with a permission
catalogue should name the Modules permission instead, because seeing where files
are kept is seeing something about every file at once:

```yaml
# config/packages/storage.yaml
storage:
    files:
        settings_permission: 'modules.manage'   # default: ROLE_ADMIN
        storage_label: 'Hetzner'                # what YOU call the place files go
        storage_location: 'Falkenstein, Germany'
        # enabled: false                        # to ship the storage without the screens
```

`storage_label` is the one place a proper noun belongs, and it comes from the
deployment: unset, the hub says *Object storage* or *This server* according to
the adapter, and never invents a vendor.

**3 · Nothing else.** The stylesheet and the behaviour script ship from the
bundle's own `public/` — AssetMapper serves them as
`bundles/uhifadhilabsstorage/files.css` and `…/files.js`, content-versioned, no
`assets:install` — and are loaded only by this bundle's own `base.html.twig`, so
the host's `app.css` never references storage. The widget chrome (`.w-grid`,
`.w-cell`, `.w-span-N`) is the host's and is already there.

### The sidebar line

The host's sidebar is hardcoded Twig; there is no extension point for a
top-level entry, so **this is a host-side edit** and no file outside this
repository has been changed. In `templates/layout.html.twig`, in the **System**
group, **above Alerts**:

```twig
<div class="nav-hd">System</div>
<a class="nav-item{{ _nav.files ? ' on' }}" href="{{ path('storage_files') }}">{{ ux_icon('lucide:image') }}<span>Files</span></a>
<span class="nav-item off" title="Alerts — planned (workflow + audit roadmap)">{{ ux_icon('lucide:bell') }}<span>Alerts</span></span>
```

and in `src/Twig/SidebarExtension.php`'s `build()`:

```php
'files' => str_starts_with($route, 'storage_files'),
```

**Why System and not Observatory** (the design left this open): the hub is
org-wide, it is not an area tab, and it administers as much as it observes.
Moving it to Observatory beside Performance is one line if the ruling goes the
other way.

### The screens

| Screen | Route | Who |
|---|---|---|
| The hub | `GET /files` | anyone signed in |
| Widget library | `GET /files/widgets` (+ 8 POSTs) | anyone signed in |
| A file's own page | `GET /files/f/{key}` | anyone signed in |
| Remove a file | `POST /files/f/{key}/remove` | whoever the owning record says |
| Where files go | `GET /files/settings` | `files.settings_permission` |

The hub is a **widget dashboard on the host's framework**: thirteen widgets in
five headed sections, and all five design directions ship as built-in presets
(`a` contact sheet, `b` owner first, `c` the ledger, `d` by the day it was
taken, `e` where the bytes are) beside the direction-neutral composition the
platform ships with. A person adopts one, copies it, and mixes a widget from
another into their copy, exactly as they do on departments or team.

Two deviations from the design's own URL sketch, both deliberate:

- A file page is `/files/f/{key}`, not `/files/{key}`. A key is a **path**, and
  a `.+` placeholder at the top level would swallow `/files/widgets` whole.
- The hub's **overlay** shows a file's facts and links to its page; it does not
  repeat the guard. What may be done to a file is a per-person answer the owning
  record gives, and it is rendered where it is authoritative — an overlay that
  cached a permission would eventually repeat it wrongly.

### Putting a module's files on the hub

One interface, one tag, and **zero** knowledge of your module in this bundle:

```php
use UhifadhiLabs\Storage\Registry\FileSourceInterface;

final class PatrolFileSource implements FileSourceInterface
{
    public function moduleSlug(): string  { return 'patrols'; }
    public function moduleLabel(): string { return 'Patrols'; }
    public function attachesTo(): string  { return 'an observation’s photographs · a patrol’s own track'; }

    public function claimsKey(string $key): bool { return str_starts_with($key, 'patrols/'); }

    /** @return iterable<FileEntry> */
    public function files(): iterable { /* yield one FileEntry per photo row */ }

    public function guard(string $key, ?UserInterface $user): FileGuard { /* your rule, in your words */ }
}
```

```php
$services->set('patrol.file_source', PatrolFileSource::class)
    ->args([service(ObservationPhotoRepository::class), service('router')])
    ->tag(FileSourceInterface::TAG);   // 'storage.file_source'
```

The tag is applied by hand because a reusable bundle is not autoconfigured; a
**host's** own service carries `#[AutoconfigureTag(FileSourceInterface::TAG)]`
on its own class instead.

`FileEntry` is what a module hands over, and the fields are the contract:

| Field | Meaning |
|---|---|
| `key` | the storage key — the file's identity everywhere, including its URL |
| `name` | what to print; the file's own name |
| `mimeType`, `byteSize` | the **detected** type, and the original's size |
| `ownerLabel`, `ownerUrl` | "OBS-0214" and its page. `null` url ⇒ named, not linked |
| `moduleSlug`, `moduleLabel` | "patrols" / "Patrols" |
| `areaSlug`, `areaLabel` | the area filter; `null` for something org-wide |
| `takenAt` | the **handset's** clock. `null` for a document, which has no such moment |
| `arrivedAt` | when it reached us — the only ordering that answers "has it synced" |
| `thumbKey` | the ONE ~400px picture, or `null` |
| `thumbState` | pass it where you track a queue (`Waiting`/`Failed`); derived otherwise |
| `caption` | the record's caption, shown here and never edited here |

`kind` (photo / document / track) is read off the detected mime type, never off
the name.

A module that ships no source simply does not appear: **the hub grows by
modules, never by folders.** A module that ships a source but holds nothing is
still listed, because "we have that and it is empty" is a different fact from
"we do not have it". A source that throws is skipped rather than fatal — one
module having a bad day must not hide every other module's files.

### Removal — remove, never delete

The design promises that removing a file is a **recorded event on the owning
record**. Storage cannot keep that promise alone: it has no records to write a
trail line onto. So removal is a second, optional interface, implemented by the
same module:

```php
final class PatrolFileSource implements FileSourceInterface, FileRemovalInterface
{
    public function remove(string $key, ?UserInterface $user, ?string $reason): void
    {
        // 1. write the removal onto the OBSERVATION's own trail
        // 2. then drop the bytes (EvidenceStorage::delete)
    }
}
```

The hub offers the control only where **both** the guard allows it and the
module ships this hook — a module that has not written its trail line yet does
not offer removal, which is the safe way round. The guard is asked **again** on
the way in, so a page's state is never the authority, and the token is minted per
file so one file's page cannot remove another's.

The four guard answers, each in the module's own words:

| `GuardStateEnum` | Means | Removal offered |
|---|---|---|
| `Locked` | the record will not let go of this file | no |
| `Reason` | yes, with a reason the record will keep | yes, reason required |
| `Allowed` | yes | yes |
| `Denied` | removable by somebody, but not by this person | no |

A key **no** installed module claims answers `Locked`: nothing here can
authorise removing a file nothing here owns.

### The design this hub is a port of

The settled design lives in the design workspace (`uhifadhi-web`): `files/`
(index, widgets, detail, settings), `files/files.widgets.js` (the surface
declaration — five groups, thirteen widgets, five presets), `presets/files/`
(the gallery and its coverage manifest), `files.css` and `assets/files.js`.

**Static-twin discipline.** Every `_w_<id>.html.twig` in `templates/files/` is
the twin of the matching `html` entry in `files.widgets.js`, and each partial's
header says so; `public/files.css` is the twin of the design's `files.css`.
Change one and change the other, or the library's preview stops being the
widget. `FilesWidgetsTest` fails if a partial loses that header, and
`WidgetLibrarySeamTest` fails if the library and the hub stop rendering the same
partial with the same context.

**Open questions the design left, answered as defaults** — each overridable
without touching a template:

| Question | Shipped answer |
|---|---|
| Where the hub lives in the sidebar | System, above Alerts |
| A per-area `/areas/{uuid}/files` tab | not in v1 — area is a filter on the hub |
| Files on a map | not in v1 — no map layer |
| Who may open the hub | anyone signed in; originals stay voter-gated |
| Removal wording | **remove**, with the record keeping the trail line |

## Adopting it in a module

> No code outside this repository has been changed. These are the steps to take
> in each module, when you choose to take them.

### patrol-module

1. **Depend on it** — add `uhifadhilabs/storage-module` to `composer.json`, and
   register both bundles in the host.

2. **Rewire `PhotoSyncService`.** Drop the `$photoDir` and `$maxBytes`
   constructor arguments (and the `patrol.photo_dir` / `patrol.photo_max_bytes`
   parameters behind them) and inject `service('storage.evidence_storage')`
   instead. Replace the `guardFile()` / `extensionFor()` / `move()` block with:

   ```php
   try {
       $stored = $this->evidence->store($file, 'patrol/'.$patrolRef, $clientUuid->toRfc4122());
   } catch (EvidenceRejectedException $e) {
       throw PatrolApiException::invalidPayload($e->getMessage(), ['clientUuid' => $clientUuid->toRfc4122(), ...$e->details]);
   } catch (EvidenceStorageFailedException $e) {
       throw new PatrolApiException(500, 'photo_storage_failed', 'The photo could not be stored.', retryable: true);
   }
   ```

   `guardFile()` and `extensionFor()` then delete outright — that is the whole
   point of the move. The duplicate-`clientUuid` check stays in patrol: it is
   about patrol's unique index, not about bytes.

3. **`ObservationPhoto`** — keep the existing relative-path column and write
   `$stored->key` into it; set `mimeType` from `$stored->mimeType` and
   `byteSize` from `$stored->byteSize`. Add a **nullable** `thumbKey` column and
   write `$stored->thumbKey`. Nullable is not optional: see
   [Thumbnails](#thumbnails).

4. **Ship `PatrolEvidenceVoter`** claiming the `patrol/` prefix, tagged
   `uhifadhi.evidence_access_voter`, resolving the key back to its
   `ObservationPhoto` → `Observation` → `Patrol` and applying patrol's own
   rules. Until this exists, patrol's photos are **denied** — which is the
   correct failure direction, but it does mean the voter ships in the same
   change as the rewire.

5. **Templates** — replace any direct path with
   `path('storage_evidence_show', {key: photo.thumbKey ?? photo.key})`.

6. **Existing rows.** Today's keys are `patrol-<uuid>/<uuid>.jpg` under
   `var/patrol/photos`; new ones will be `patrol/<ref>/<uuid>.jpg` under
   `var/storage/evidence`. Either move the files and rewrite the column in a
   migration, or — for a gentler cut-over — point
   `storage.evidence.directory` at the existing `var/patrol/photos` and have
   `PatrolEvidenceVoter::claimsKey()` also claim the legacy `patrol-` prefix.

7. **One behaviour change, on purpose.** Patrol currently records
   `$file->getClientMimeType()` — the type the *client claimed* — while
   validating the detected one. `$stored->mimeType` is the **detected** type, so
   after adoption that column holds the truth rather than the claim. Everything
   else is byte-for-byte the same set of accepted and rejected uploads.

8. **Put patrol's files on the hub** — a `PatrolFileSource` implementing
   [`FileSourceInterface`](#putting-a-modules-files-on-the-hub), tagged
   `storage.file_source`, yielding one `FileEntry` per `ObservationPhoto` **and**
   one per patrol track export:

   - `ownerLabel` / `ownerUrl` — `OBS-0214` and `path('patrol_observation_show', …)`
     for a photograph; `PTL-0451` and `path('patrol_show', …)` for a track.
   - `takenAt` — the **handset's** `takenAt`, never `createdAt`; `arrivedAt` is
     the sync time.
   - `areaSlug` / `areaLabel` — the patrol's area.
   - `attachesTo()` — "an observation's photographs · a patrol's own track".
   - `guard()` — a filed observation's photographs are `Reason` (the observation
     keeps a line saying who removed which one and why); **a patrol's own track
     is `Locked`**, because the patrols module treats it as part of the patrol
     rather than as an attachment.
   - Implement `FileRemovalInterface` in the same class once the observation can
     record a removal on its own trail; until then the hub will not offer
     removal, which is the safe way round.

   The voter (step 4) and the source answer two different questions — *may you
   read these bytes* and *may you take this file off its record* — and both stay
   in patrol.

### incident-module

Incidents has no photo entity yet — the design has not been ruled on, and the
design drives the data model. When it is:

1. Depend on `uhifadhilabs/storage-module`.
2. Give the evidence entity `key` (string), `thumbKey` (**nullable** string),
   `mimeType` and `byteSize` columns. Do not store absolute paths.
3. Call `store($file, 'incident/'.$incident->getUuid(), $photoUuid)`.
4. Ship an `IncidentEvidenceVoter` claiming the `incident/` prefix, tagged
   `uhifadhi.evidence_access_voter`.
5. Ship an `IncidentFileSource` implementing
   [`FileSourceInterface`](#putting-a-modules-files-on-the-hub), tagged
   `storage.file_source`. `attachesTo()` is "evidence — photographs and signed
   documents"; a document has no `takenAt`, so pass `null` and let it sit under
   the day it was filed. `guard()` is where the incidents module's own rule
   lives, and it is the rule the design drew: an incident **still in progress**
   answers `Locked` and will not let go of evidence a claim rests on, a
   **resolved and filed** one answers `Allowed`, and evidence uploaded by another
   department answers `Denied` for anyone outside it.
6. Implement `FileRemovalInterface` alongside it, writing the removal onto the
   incident's own trail before dropping the bytes.

`EvidenceKindEnum` already exists in incidents and is orthogonal to this: it
classifies what a piece of evidence *is*, while the key prefix records who owns
the bytes.

## Service reference

| Service id | Class | Notes |
|---|---|---|
| `storage.evidence` | `League\Flysystem\Filesystem` | The named storage. Private on both visibility axes. |
| `storage.evidence_storage` | `EvidenceStorage` | The API modules consume. Aliased by FQCN. |
| `storage.evidence_constraints` | `EvidenceConstraints` | The reusable guard. Aliased by FQCN. |
| `storage.thumbnail_generator` | `ThumbnailGenerator` | Imagick, then GD. |
| `storage.evidence_access_decider` | `EvidenceAccessDecider` | Collects the tagged voters. |
| `storage.s3_client` | `AsyncAws\S3\S3Client` | Defined only when `adapter: s3`. |

Tag: `uhifadhi.evidence_access_voter`.
Route: `storage_evidence_show` — registered only when SecurityBundle is present.

## Development

```console
composer check     # cs:check + phpstan (max) + phpunit
```

No database is needed anywhere: this bundle owns no entities. The integration
suite writes real bytes into a temp directory, because a mocked filesystem would
only be testing the mock.

CI runs PHP 8.4 and 8.5 with **GD but deliberately without Imagick**, so the
"a HEIC arrived and nothing here can decode it" branch is exercised on every
run rather than only on the machines that happen to lack the extension.