<?php

namespace App\Entity;

use App\Enum\ProblemeIntervention;
use App\Enum\StatutIntervention;
use App\Repository\InterventionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\OneToOne(mappedBy: 'Intervention', cascade: ['persist', 'remove'])]
    private ?Operation $operation = null;

    #[ORM\OneToOne(mappedBy: 'intervention', cascade: ['persist', 'remove'])]
    private ?Diagnostic $diagnostic = null;

    #[ORM\ManyToOne(inversedBy: 'interventions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Appareil $appareil = null;

    #[ORM\ManyToOne(inversedBy: 'interventions')]
    private ?Employe $employe = null;

    /**
     * @var Collection<int, MaterielIntervention>
     */
    #[ORM\OneToMany(targetEntity: MaterielIntervention::class, mappedBy: 'intervention')]
    private Collection $materielInterventions;

    public function __construct()
    {
        $this->materielInterventions = new ArrayCollection();
    }

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

    public function getOperation(): ?Operation
    {
        return $this->operation;
    }

    public function setOperation(Operation $operation): static
    {
        // set the owning side of the relation if necessary
        if ($operation->getIntervention() !== $this) {
            $operation->setIntervention($this);
        }

        $this->operation = $operation;

        return $this;
    }

    public function getDiagnostic(): ?Diagnostic
    {
        return $this->diagnostic;
    }

    public function setDiagnostic(Diagnostic $diagnostic): static
    {
        // set the owning side of the relation if necessary
        if ($diagnostic->getIntervention() !== $this) {
            $diagnostic->setIntervention($this);
        }

        $this->diagnostic = $diagnostic;

        return $this;
    }

    public function getAppareil(): ?Appareil
    {
        return $this->appareil;
    }

    public function setAppareil(?Appareil $appareil): static
    {
        $this->appareil = $appareil;

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

    /**
     * @return Collection<int, MaterielIntervention>
     */
    public function getMaterielInterventions(): Collection
    {
        return $this->materielInterventions;
    }

    public function addMaterielIntervention(MaterielIntervention $materielIntervention): static
    {
        if (!$this->materielInterventions->contains($materielIntervention)) {
            $this->materielInterventions->add($materielIntervention);
            $materielIntervention->setIntervention($this);
        }

        return $this;
    }

    public function removeMaterielIntervention(MaterielIntervention $materielIntervention): static
    {
        if ($this->materielInterventions->removeElement($materielIntervention)) {
            // set the owning side to null (unless already changed)
            if ($materielIntervention->getIntervention() === $this) {
                $materielIntervention->setIntervention(null);
            }
        }

        return $this;
    }
}
