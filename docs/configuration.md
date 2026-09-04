# Configuration

Everything below is optional — an unconfigured host gets a working, private,
on-disk store. Configuration lives in `config/packages/storage.yaml`.

Two things are deliberately **not** configurable: there is no `visibility` key
and no `public_url` key. The evidence storage is private by construction,
because "private unless someone remembers to say so" is how deployments end up
serving field photographs to the open internet, and a public URL would route
around the permission seam entirely.

## Local (default)

```yaml
storage:
    evidence:
        adapter: local
        directory: '%kernel.project_dir%/var/storage/evidence'   # outside the document root, always
        max_bytes: 12582912          # 12 MiB
        thumbnail_long_edge: 400
        allowed_mime_types: ['image/jpeg', 'image/png', 'image/heic', 'image/heif', 'image/webp']
```

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
