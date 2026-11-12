<?php

namespace App\Entity;

use App\Repository\OperationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OperationRepository::class)]
class Operation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateOperation = null;

    #[ORM\Column]
    private ?int $dureeOperation = null;

    #[ORM\OneToOne(inversedBy: 'operation', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Intervention $Intervention = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateOperation(): ?\DateTimeImmutable
    {
        return $this->dateOperation;
    }

    public function setDateOperation(\DateTimeImmutable $dateOperation): static
    {
        $this->dateOperation = $dateOperation;

        return $this;
    }

    public function getDureeOperation(): ?int
    {
        return $this->dureeOperation;
    }

    public function setDureeOperation(int $dureeOperation): static
    {
        $this->dureeOperation = $dureeOperation;

        return $this;
    }

    public function getIntervention(): ?Intervention
    {
        return $this->Intervention;
    }

    public function setIntervention(Intervention $Intervention): static
    {
        $this->Intervention = $Intervention;

        return $this;
    }
}
