<?php

namespace App\Entity;

use App\Enum\ProblemeIntervention;
use App\Enum\StatutIntervention;
use App\Repository\InterventionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InterventionRepository::class)]
class Intervention
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: ProblemeIntervention::class)]
    private ?ProblemeIntervention $probleme = null;

    #[ORM\Column(enumType: StatutIntervention::class)]
    private ?StatutIntervention $statut = null;

    #[ORM\Column(length: 10000)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProbleme(): ?ProblemeIntervention
    {
        return $this->probleme;
    }

    public function setProbleme(ProblemeIntervention $probleme): static
    {
        $this->probleme = $probleme;

        return $this;
    }

    public function getStatut(): ?StatutIntervention
    {
        return $this->statut;
    }

    public function setStatut(StatutIntervention $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
