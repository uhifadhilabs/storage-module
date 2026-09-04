# Serving, and the permission seam

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
namespace Uhifadhi\Patrol\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Uhifadhi\Storage\Security\EvidenceAccessVoterInterface;

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
