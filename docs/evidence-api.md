# The evidence API

```php
use Uhifadhi\Storage\Service\EvidenceStorage;

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

## Contents

- [Validation](#validation)
- [Thumbnails](#thumbnails)

## Validation

`EvidenceConstraints` carries the allowlist and the size cap, and is reusable on
its own for a caller that wants to validate before committing to an upload:

```php
$constraints->validate($file);              // throws EvidenceRejectedException
$constraints->allows('image/heic');         // bool
EvidenceConstraints::extensionFor($mime);   // 'jpg' for image/jpeg, 'pdf' for application/pdf, …
```

The three checks are **deliberately the semantics patrol-module already
applies** in `PhotoSyncService::guardFile()` — the same checks in the same
order, the same five default types, the same "detected type, never the
filename" rule, and the same tolerance of a file whose type cannot be detected
at all. Patrol can adopt this class and reject exactly what it rejected before.

`validate()` also splits the two size limits the way
`UploadedFile::getErrorMessage()` does: an `UPLOAD_ERR_INI_SIZE` /
`UPLOAD_ERR_FORM_SIZE` upload is refused with reason `exceeds_server_limit` and a
sentence naming php.ini's own ceiling, while a truncated transfer keeps
`upload_incomplete`. A caller that reads them alike blames the network for
[a php.ini line](configuration.md#the-two-size-limits).

The extension is derived from the detected type because a filename is
attacker-controlled text, and letting it choose is how an upload directory ends
up holding a `.php`. It is looked up in `symfony/mime`'s table, so a deployment
that widens `allowed_mime_types` gets the right one — a signed PDF is keyed
`.pdf`, not `.jpg`. A type that is allowed but that nothing can name an
extension for is **refused** (`EvidenceRejectedException`, reason
`unsupported_type`) rather than stored under a guessed one. A file whose type
cannot be detected at all is still accepted, and is keyed `.bin` — the
extension of the `application/octet-stream` it is recorded as.

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
