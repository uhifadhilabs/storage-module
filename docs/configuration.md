# Configuration

Everything below is optional — an unconfigured host gets a working, private,
on-disk store. Configuration lives in `config/packages/storage.yaml`.

Two things are deliberately **not** configurable: there is no `visibility` key
and no `public_url` key. The evidence storage is private by construction,
because "private unless someone remembers to say so" is how deployments end up
serving field photographs to the open internet, and a public URL would route
around the permission contribution point entirely.

## Contents

- [Local (default)](#local-default)
- [S3-compatible object storage](#s3-compatible-object-storage)

## Local (default)

```yaml
storage:
    evidence:
        adapter: local
        directory: '%kernel.project_dir%/var/storage/evidence'   # outside the document root, always
        max_bytes: 12582912          # 12 MiB
        thumbnail_long_edge: 400
        allowed_mime_types:
            - 'image/jpeg'
            - 'image/png'
            - 'image/heic'
            - 'image/heif'
            - 'image/webp'
            - 'application/pdf'
            - 'application/gpx+xml'
            - 'application/xml'
            - 'text/xml'
```

**The default is one of each kind the Files hub names.** Its filter row reads
*Photos · Documents · Tracks*, so a deployment that has configured nothing can
receive one of each; a default that covered only photographs made the other two
chips permanently empty and — worse — silently overruled any module whose target
genuinely took a GPX, refusing it with a sentence about photographs.

**The last two are carriers, not kinds.** `fileinfo` reads BYTES and has never
heard of GPX, so a real track file detects as `text/xml` (or
`application/xml`). They are on the list so a track can get through, and nothing
that speaks to a person counts them as a kind of their own: the upload zone's
line reads `… · gpx`, never `… · xml`, and a refusal says "not a GPX track".
A consequence worth knowing: such a file is keyed `.xml`, because the key names
what the bytes are and the bytes are XML.

`allowed_mime_types` may be **narrowed** — a deployment that takes photographs
only says so and gets exactly that, sentences included — or widened further. A
widened type is stored under its own extension and simply gets no thumbnail
unless an engine can read it. A type nothing can name an extension for is refused
by `store()` rather than stored under a guessed one; see
[the evidence API](evidence-api.md#validation).

## S3-compatible object storage

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
