# Charter — what belongs here and what does not

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
[the permission seam](serving-and-permissions.md).