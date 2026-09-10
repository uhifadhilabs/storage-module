<?php

declare(strict_types=1);

/*
 * This file is part of the UhifadhiLabs Storage Module.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Storage\Tests\Integration\Fixtures\Area;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * THE HOST'S AREA, PLAYED BY A STAND-IN — the area a sibling module's record
 * points at, present in this suite only so the schema can be built.
 *
 * Storage owns no entities and maps no tables, but its test kernel installs
 * uhifadhi/team-module for the account class, and team's Department now carries
 * a nullable association to the platform's {@see AreaInterface} (module-contracts).
 * A real installation resolves that interface to its own area class through
 * `doctrine.orm.resolve_target_entities`, and uhifadhi/area-module is the
 * package that answers it. This suite installs neither area-module nor the seam,
 * so — exactly as team's own test kernel does — it plays the host itself: a real
 * entity implementing the interface, resolved to by the kernel alone.
 *
 * It mirrors team-module's own fixture area: a sequential id the association is
 * built on, and a public UUIDv7 for addressing. The interface asks for nothing
 * but identity, a name and a public address, so everything past those is the
 * host's business and is deliberately absent.
 */
#[ORM\Entity]
#[ORM\Table(name: 'fixture_host_area')]
#[ORM\HasLifecycleCallbacks]
class HostArea implements AreaInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $uuid = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?Uuid
    {
        return $this->uuid;
    }

    public function getUuidString(): ?string
    {
        return $this->uuid?->toRfc4122();
    }

    #[ORM\PrePersist]
    public function generateUuid(): void
    {
        $this->uuid ??= Uuid::v7();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
