# Service reference

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
