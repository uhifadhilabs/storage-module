# Adopting it in a module

> No code outside this repository has been changed. These are the steps to take
> in each module, when you choose to take them.

## patrol-module

1. **Depend on it** — add `uhifadhi/storage-module` to `composer.json`, and
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
   [Thumbnails](evidence-api.md#thumbnails).

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
   [`FileSourceInterface`](files-hub.md#putting-a-modules-files-on-the-hub), tagged
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

## incident-module

Incidents has no photo entity yet — the design has not been ruled on, and the
design drives the data model. When it is:

1. Depend on `uhifadhi/storage-module`.
2. Give the evidence entity `key` (string), `thumbKey` (**nullable** string),
   `mimeType` and `byteSize` columns. Do not store absolute paths.
3. Call `store($file, 'incident/'.$incident->getUuid(), $photoUuid)`.
4. Ship an `IncidentEvidenceVoter` claiming the `incident/` prefix, tagged
   `uhifadhi.evidence_access_voter`.
5. Ship an `IncidentFileSource` implementing
   [`FileSourceInterface`](files-hub.md#putting-a-modules-files-on-the-hub), tagged
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
