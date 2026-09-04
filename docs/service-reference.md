# Service reference

| Service id | Class | Notes |
|---|---|---|
| `storage.evidence` | `League\Flysystem\Filesystem` | The named storage. Private on both visibility axes. |
| `storage.evidence_storage` | `EvidenceStorage` | The API modules consume. Aliased by FQCN. |
| `storage.evidence_constraints` | `EvidenceConstraints` | The reusable guard. Aliased by FQCN. |
| `storage.thumbnail_generator` | `ThumbnailGenerator` | Imagick, then GD. |
| `storage.evidence_access_decider` | `EvidenceAccessDecider` | Collects the tagged voters. |
| `storage.s3_client` | `AsyncAws\S3\S3Client` | Defined only when `adapter: s3`. |
| `storage.file_registry` | `FileRegistry` | Every installed module's files, read live. |
| `storage.files_surface` | `FilesSurface` | The context every hub widget renders from. |
| `storage.settings` | `StorageSettings` | "Where files go", from configuration only. |
| `storage.twig_extension` | `FilesExtension` | Registered wherever there is a Twig. |
| `storage.widget_surface` | `FilesWidgets` | The `files` dashboard. Hub screens only. |
| `storage.navigation` | `FilesNavigation` | The sidebar's Files row. Hub screens only, and only where a shell is installed. |

Tags:

| Tag | What carries it |
|---|---|
| `uhifadhi.evidence_access_voter` | a module's voter, answering for its own key prefix |
| `storage.file_source` | a module's `FileSourceInterface`, putting its files on the hub |
| `uhifadhi.widget_surface` | `storage.widget_surface` — the `files` dashboard, declared into `uhifadhi/widget-module` |
| `shell.nav_section` | `storage.navigation` — the sidebar row, declared into `uhifadhi/shell-module` |

Routes: `storage_evidence_show` — registered only when SecurityBundle is present.
The hub's own eleven (`storage_files`, `storage_files_widgets` and the eight
writes behind it, `storage_files_show`, `storage_files_remove`,
`storage_files_settings`) are registered only where SecurityBundle and TwigBundle
both are.
