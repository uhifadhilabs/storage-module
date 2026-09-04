# The Files hub

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

## What a host wires

The screens register themselves where **SecurityBundle**, **TwigBundle** and the
host's **widget framework** (`Uhifadhi\Service\WidgetService` /
`WidgetEndpoint`) are all present, and are simply absent otherwise — a host
without one of them gets no half-working dashboard.

**1 · Mount the routes** (the same file that mounts the serving route):

```yaml
# config/routes/storage.yaml
storage:
    resource: '@UhifadhiStorageBundle/src/Controller/'
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
`bundles/uhifadhistorage/files.css` and `…/files.js`, content-versioned, no
`assets:install` — and are loaded only by this bundle's own `base.html.twig`, so
the host's `app.css` never references storage. The widget chrome (`.w-grid`,
`.w-cell`, `.w-span-N`) is the host's and is already there.

## The sidebar line

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

## The screens

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

## Putting a module's files on the hub

One interface, one tag, and **zero** knowledge of your module in this bundle:

```php
use Uhifadhi\Storage\Registry\FileSourceInterface;

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

## Removal — remove, never delete

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

## The design this hub is a port of

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
