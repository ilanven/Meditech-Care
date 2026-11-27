<?php

declare(strict_types=1);

namespace App\Dto\Employe;

use App\Enum\Poste;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/** Contrat d'entrée de POST */
#[OA\Schema(
    description: 'Création d’un employé. Tous les champs sont obligatoires, sauf $poste qui vaut Technicien et $actif qui vaut true si absents. $id est généré automatiquement',
    required: ['nom', 'prenom', 'telephone', 'email', 'poste'],
)]
final class CreateEmployeInput
{
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, max: 255)]
    #[OA\Property(type: 'string', minLength: 2, maxLength: 255, example: 'Dupont')]
    public string $nom = '';

    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(min: 2, max: 255)]
    #[OA\Property(type: 'string', minLength: 2, maxLength: 255, example: 'Jean')]
    public string $prenom = '';

    #[Assert\NotBlank(message: 'Le téléphone est obligatoire.')]
    #[Assert\Length(min: 8, max: 20)]
    #[OA\Property(type: 'string', minLength: 8, maxLength: 20, example: '+33601020304')]
    public string $telephone = '';

    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'L\'email doit être valide')]
    #[Assert\Length(min: 2, max: 180)]
    #[OA\Property(type: 'string', minLength: 2, maxLength: 180, example: 'jean.dupont@email.com')]
    public string $email = '';

    #[OA\Property(
        type: 'string',
        enum: Poste::class,
        example: 'Technicien'
    )]
    public ?Poste $poste = Poste::Technicien;

    #[OA\Property(type: 'boolean', default: true, example: true)]
    public bool $actif = true;
}
