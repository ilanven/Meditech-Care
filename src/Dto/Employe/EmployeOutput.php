<?php

declare(strict_types=1);

namespace App\Dto\Employe;

use App\Entity\Employe;
use OpenApi\Attributes as OA;
use App\Enum\Poste;

/** DTO de sortie : la forme JSON ne dépend plus directement de l'entité Doctrine. */
#[OA\Schema(description: 'Représentation publique d’un employe', required: ['id', 'nom', 'prenom', 'telephone', 'email', 'poste','actif'])]
final readonly class EmployeOutput
{
    public function __construct(
        #[OA\Property(type: 'integer', example: 1)] public int $id,
        #[OA\Property(type: 'string', example: 'DUPONT')] public string $nom,
        #[OA\Property(type: 'string', example: 'Jean')] public string $prenom,
        #[OA\Property(type: 'string', example: '0612345678')] public string $telephone,
        #[OA\Property(type: 'string', example: 'jean.dupont@email.fr')] public string $email,
        #[OA\Property(type: 'string', enum: POSTE::class, example: 'Technicien')] public string $poste,
        #[OA\Property(type: 'boolean', example: true)] public bool $actif,
    ) {
    }

    public static function fromEntity(Employe $employe): self
    {
        $id = $employe->getId();
        $nom = $employe->getNom();
        $prenom= $employe->getPrenom();
        $telephone = $employe->getTelephone();
        $email = $employe->getEmail();
        $poste = $employe->getPoste()->value;
        $actif = $employe->isActif();

        // Doctrine déclare ces propriétés nullable durant la vie d'une entité
        // neuve. Pour une réponse publique, elles doivent avoir une vraie valeur.
        if ($id === null || $nom === null || $prenom === null || $telephone === null || $email === null || $poste === null || $actif === null) {
            throw new \LogicException('Impossible de publier un produit incomplet.');
        }

        return new self($id, $nom, $prenom, $telephone, $email, $poste, $actif);
    }
}
