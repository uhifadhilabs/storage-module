# Uploads

Storage owns the whole upload feature: the component a person drops a file on,
the one endpoint the bytes go to, and the contract by which a module says what a
file may become. A module adds uploads anywhere by implementing **one interface**
and writing **one Twig line** — no controller, no route, no JavaScript, no
stylesheet.

## Contents

- [Why storage owns it](#why-storage-owns-it)
- [The contract](#the-contract)
- [A worked module](#a-worked-module)
- [The Twig line](#the-twig-line)
- [The two presentations](#the-two-presentations)
- [The endpoint](#the-endpoint)
- [Refusals](#refusals)
- [Events](#events)
- [Installing the controller](#installing-the-controller)
- [What this replaces](#what-this-replaces)

## Why storage owns it

A file used to enter the product through five doors, and each door had drawn its
own box: a dropzone at 28px in one stylesheet, the same dropzone at 22px in
another, a dashed tile in a third with no picker, no progress, no error and no
way to take a file back off. The same file behaved differently depending on
which door it came through, and one of the doors could not be reversed at all.

So there is one component, owned by the bundle that owns the bytes. What a module
still decides is everything only a module can know — which record, who may, what
kinds, and what the file *becomes* — and it says all of that through one
interface.

## The contract

`Uhifadhi\Storage\Upload\UploadTargetInterface`. Seven questions:

| Method | Answers |
|---|---|
| `kind(): string` | the target key prefix — `incident`, `observation`, `patrol-track`, `zone-boundary`. It is the half of a target string before the colon AND the first segment of every key the target's files are stored under. |
| `accepts(string $targetId): ?object` | resolves the half after the colon to the owning record, or null. Null means "no such record", never "you may not". |
| `mayUpload(object $record, UserInterface $user): bool` | may this person attach a file here. |
| `constraints(object $record): UploadConstraints` | what this record takes: allowed mime types, largest single file, how many at once. `UploadConstraints::from($deployment)` inherits the installation's own list, which by default covers all three of the hub's kinds. |
| `received(object $record, StoredFile $file, UserInterface $user): UploadReceipt` | the bytes are stored — write the row, and say what the file became. |
| `mayRemove(string $key, UserInterface $user): bool` | may this person take this file back off. A different question from `mayUpload()`: a verified case may well accept evidence and refuse to release it. |
| `removed(string $key, UserInterface $user): void` | the file is going — unpick the record. Called BEFORE the bytes are deleted, so a module that refuses after all can throw and leave the file where it is. |

**`kind()` doubles as the key prefix on purpose.** Every file a target receives
is stored under `<kind>/<targetId>/…`, so `EvidenceKey::rootSegment()` reads the
owning module back OUT of a stored key. That is how a removal routes to the right
module with nothing but the key, and it is the same prefix
[`EvidenceAccessVoterInterface`](serving-and-permissions.md) and
[`FileSourceInterface`](files-hub.md) already claim on. Write the prefix once,
in the target, and have the voter and the file source read it back.

**A kind nobody claims is refused, and so is a kind two modules claim.** Both are
"no target", for the same reason the permission contract denies an unclaimed key:
attaching a file to a record nobody owns is worse than not attaching it.

## A worked module

The example is synthetic — a module that keeps sightings, each with photographs.

```php
// src/Upload/SightingEvidenceTarget.php
namespace Example\Sighting\Upload;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Uhifadhi\Storage\Model\EvidenceConstraints;
use Uhifadhi\Storage\Model\StoredFile;
use Uhifadhi\Storage\Model\UploadConstraints;
use Uhifadhi\Storage\Model\UploadReceipt;
use Uhifadhi\Storage\Upload\UploadTargetInterface;

final readonly class SightingEvidenceTarget implements UploadTargetInterface
{
    public const string KIND = 'sighting';

    public function __construct(
        private SightingRepository $sightings,
        private SightingPhotoService $photos,
        private AuthorizationCheckerInterface $authorization,
        private EvidenceConstraints $deployment,
    ) {
    }

    public function kind(): string
    {
        return self::KIND;
    }

    public function accepts(string $targetId): ?object
    {
        return Uuid::isValid($targetId) ? $this->sightings->findOneByUuid(Uuid::fromString($targetId)) : null;
    }

    public function mayUpload(object $record, UserInterface $user): bool
    {
        return $record instanceof Sighting && $this->authorization->isGranted('sightings.record', $record);
    }

    public function constraints(object $record): UploadConstraints
    {
        // The deployment's own allowlist and cap — photographs, documents and
        // tracks unless the installation narrowed it. Narrow further only where
        // the record genuinely takes less; widening promises what the storage
        // will then refuse.
        //
        // A TRACKS-ONLY TARGET NAMES THE CARRIERS TOO, or it refuses every GPX
        // ever dropped on it — fileinfo reads bytes, and a GPX's bytes are xml:
        //     ['application/gpx+xml', ...FileKindEnum::TRACK_CARRIERS]
        return UploadConstraints::from($this->deployment);
    }

    public function received(object $record, StoredFile $file, UserInterface $user): UploadReceipt
    {
        $photo = $this->photos->attach($record, $file, $user);

        return UploadReceipt::stored($photo->getFilename(), $this->urls->generate('sighting_show', [...]));
    }

    public function mayRemove(string $key, UserInterface $user): bool
    {
        $photo = $this->photos->findOneByPath($key);

        return null !== $photo && $this->authorization->isGranted('sightings.record', $photo->getSighting());
    }

    public function removed(string $key, UserInterface $user): void
    {
        $this->photos->detach($key, $user);
    }
}
```

Tagged by hand, because a reusable bundle is not autoconfigured
(<https://symfony.com/doc/current/bundles/best_practices.html>):

```php
// config/services.php
$services->set('sighting.upload_target', SightingEvidenceTarget::class)
    ->args([
        service(SightingRepository::class),
        service('sighting.photo_service'),
        service('security.authorization_checker'),
        service(EvidenceConstraints::class),
    ])
    ->tag(UploadTargetInterface::TAG);
```

A host's own service carries `#[AutoconfigureTag(UploadTargetInterface::TAG)]` on
its own class instead — attributes are read off the definition's class and PHP
does not inherit them from an implemented interface.

### The receipt

`UploadReceipt::stored($label, $href, $kind)` — the bytes are the thing and they
stay. `UploadReceipt::parsed($label, $href, $kind)` — the module took what it
needed and the storage deletes the original and its preview. A boundary import
that turns a GPX into geometry says `parsed`, and the design's word for that
outcome appears on the finished row's chip.

## The Twig line

```twig
{{ render_upload('sighting:' ~ sighting.uuid, 'tile') }}
```

That is the whole of the module's front end. `render_upload(target,
presentation, attrs)`:

- **`target`** — `"<kind>:<recordId>"`, or a kind alone where the target has no
  record behind it.
- **`presentation`** — `'zone'` (default) or `'tile'`.
- **`attrs`** — extra attributes for the component's own element, plus two the
  component consumes: `label` (the add tile's word, or the zone's lead) and
  `input` (the name of a hidden input a finished upload writes its key into, for
  a page that posts a classic form rather than listening for the event).

**A target that will not open draws nothing** — not a broken box, not an error
and not a greyed-out one. A kind no installed module claims, a record that is not
there, and a person the record will not take a file from are all pages that
should simply not offer an upload; `mayUpload()` is asked at render time as well
as at the endpoint. A greyed-out dropzone would tell a reader that a screen
exists and they are not trusted with it, which is a worse product than not
mentioning it.

## The two presentations

**The dropzone card** (`'zone'`) is for a step where receiving the file IS the
step: an import, a document. It states the allowed kinds and the size limit up
front — never discovered by being refused — draws a queue of one row per file,
each with its own bar and percentage and cancel, and finishes with a chip
carrying the module's word for what the file became.

**The add tile** (`'tile'`) is one cell of a grid of things already attached. It
is exactly the size and radius of its neighbours in every state, because a grid
that reflowed under the pointer would drop the file somewhere else. While a file
is in flight the tile itself is the progress surface; when it lands it becomes an
ordinary evidence tile with a remove; and the removal question is asked inside
the tile's own box — never a browser dialog.

**Tiles the module drew itself gain a working remove for free.** Put the hook on
the tile's own button and the component beside it in the grid handles the rest:

```twig
<span class="i-ph">
    {{ ux_icon('…') }}<i>{{ photo.filename }}</i>
    <button type="button" class="rm" data-upl-remove data-upl-key="{{ photo.path }}"
            aria-label="Remove this evidence">&times;</button>
</span>
```

**The vocabulary is the shell's.** `.upl-zone`, `.upl-file`, `.upl-bar`,
`.upl-act`, `.upl-tile` and `.upl-kinds` ship in `shell.css`, ahead of this
component, because a file enters through more than one door and the box belongs
to the frame. A module must not redefine any of them.

## The endpoint

```
POST   /files/upload    multipart: target, file
DELETE /files/{key}
```

Both are CSRF-protected. The token travels in an `X-CSRF-Token` header, with the
conventional `_token` field as a fallback — a `DELETE` has no body to put a field
in and a multipart `POST` does. Both are registered only where SecurityBundle is
in the kernel: a write endpoint that cannot tell a signed-in ranger from a
stranger must not exist at all.

A successful upload replies:

```json
{"key": "sighting/0199…/0199….jpg", "label": "IMG_1204.jpg", "href": "/…",
 "kind": "stored", "bytes": 3145728, "thumbnail": "/storage/evidence/…"}
```

A successful removal replies `{"key": "…"}`. Everything else replies
`{"error": "…"}` with a status code.

The order of the questions is the security property, and it is
`UploadService`'s: who → which record → may they → what and how big → store →
tell the module → honour the receipt. A file is refused **before a byte is
written**, and if the module's own row cannot be written the bytes are taken
away again rather than left as an orphan no page can reach and no removal can
route to.

## Refusals

Every refusal is **one sentence written by whoever refused**, drawn on the row or
in the tile that caused it. The component never invents an error message.

| Situation | Sentence | Status |
|---|---|---|
| kind not allowed | `That file is not a GPX track. Nothing was written.` | 422 |
| kind not allowed, inside one kind | `That file is not one of png · webp. Nothing was written.` | 422 |
| too large | `Larger than the 12.6 MB limit this storage accepts. Nothing was written.` | 422 |
| over the SERVER's limit | `That file is larger than this server accepts (2.1 MB). Nothing was written.` | 422 |
| upload truncated | `That upload did not arrive intact. Nothing was written.` | 422 |
| no file at all | `No file arrived with that upload. Nothing was written.` | 422 |
| target unknown | `Nothing on this platform answers for that target. Nothing was written.` | 404 |
| no such record | `No record answers to that target. Nothing was written.` | 404 |
| not permitted | `You may not attach a file to this record. Nothing was written.` | 403 |
| the store failed | `The storage could not keep that file. Nothing was written.` | 500 |
| removal not permitted | `You may not take that file off this record.` | 403 |
| no such file | `Nothing on this platform holds that file.` | 404 |

Every upload refusal ends with *what did not happen*, because the thing a person
needs to know after a failure is whether they now have half a record.

**A size refusal names the limit that actually refused.** There are two, and only
one of them is this module's: PHP's own `upload_max_filesize` / `post_max_size`
truncate an upload before a line of this bundle runs, and report it through the
same failed check as a broken transfer. Reading the two alike told a ranger their
upload had been damaged when the truth was an ini line — so a size code names the
server's ceiling, everything else keeps *did not arrive intact*, and the
installation is told once per process in the application log. See
[the two size limits](configuration.md#the-two-size-limits).

**A kind refusal names what the TARGET takes, never what the file is.** Somebody
holding a file that did not work needs to know which one would have. The noun is
read from the same `allowedMimeTypes` the zone printed its kinds line from, so
the promise and the refusal cannot disagree — `UploadConstraints::refusalNounFor()`
and `UploadConstraints::extensions()` are two readings of one list.

It has two shapes, and the second exists to stay true. Normally the kinds are the
answer: *a photograph*, *a photograph or document*, *a photograph, document or
GPX track*. But a target may narrow INSIDE a kind — one that takes PNGs and not
JPEGs — and "that file is not a photograph" would then be a sentence about a
photograph; there the spellings are named instead.

The **one** sentence the component writes for itself is the queue cap — `Only 10
files at a time. Nothing was written.` — because the queue is the only thing here
the server has no opinion about: each request carries one file.

## Events

A page reacts without JavaScript of its own by listening on the component's
element:

| Event | Detail |
|---|---|
| `storage:upload:init` | before anything is wired, so a page may still adjust the element |
| `storage:upload:connect` | the component is live |
| `storage:upload:done` | the whole receipt: `key`, `label`, `href`, `kind`, `bytes`, `thumbnail` |
| `storage:upload:failed` | `name` (or `key`) and the `error` sentence that was drawn |
| `storage:upload:removed` | `key` |

The names are published as constants on `Uhifadhi\Storage\Upload\UploadDom`,
alongside every data attribute that crosses the PHP↔JS boundary. `UploadSeamTest`
asserts, as text, that the constant, the template and the shipped asset all carry
the same string — a green HTTP suite has sat on top of a broken page in this
fleet before, and that test is the lesson.

## Installing the controller

The Stimulus controller ships in this bundle's `assets/` under the AssetMapper
namespace `@uhifadhi/storage-module`. The installation enables it in
`assets/controllers.json`, which the recipe writes:

```json
{
    "controllers": {
        "@uhifadhi/storage-module": {
            "preview": { "enabled": true, "fetch": "eager" },
            "upload": { "enabled": true, "fetch": "eager" }
        }
    },
    "entrypoints": []
}
```

`eager`, not `lazy`: the component must be live before a person can drop a file
on it, and a lazy controller that has not loaded yet is a box that swallows the
first drop.

## What this replaces

`.dropz` leaves `patrols.css` and `zones.css`; `.i-ph.add` leaves
`incidents.css`; `.filechip` — written twice, identically, in two stylesheets —
is replaced by `.upl-file`. `.i-ph` itself stays: a tile showing a file already
attached is the owning module's, and `.upl-tile.done` matches its box exactly so
the two sit in one grid.
