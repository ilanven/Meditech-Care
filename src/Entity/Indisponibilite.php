<?php

namespace App\Entity;

use App\Enum\TypeIndisponibilite;
use App\Repository\IndisponibiliteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IndisponibiliteRepository::class)]
class Indisponibilite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $debut = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $fin = null;

    #[ORM\Column(enumType: TypeIndisponibilite::class)]
    private ?TypeIndisponibilite $type = null;

    #[ORM\ManyToOne(inversedBy: 'indisponibilites')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Employe $employe = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDebut(): ?\DateTimeImmutable
    {
        return $this->debut;
    }

    public function setDebut(\DateTimeImmutable $debut): static
    {
        $this->debut = $debut;

        return $this;
    }

    public function getFin(): ?\DateTimeImmutable
    {
        return $this->fin;
    }

    public function setFin(\DateTimeImmutable $fin): static
    {
        $this->fin = $fin;

        return $this;
    }

    public function getType(): ?TypeIndisponibilite
    {
        return $this->type;
    }

    public function setType(TypeIndisponibilite $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getEmploye(): ?Employe
    {
        return $this->employe;
    }

    public function setEmploye(?Employe $employe): static
    {
        $this->employe = $employe;

        return $this;
    }
}
