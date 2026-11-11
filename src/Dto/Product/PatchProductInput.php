<?php

declare(strict_types=1);

namespace App\Dto\Product;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO de PATCH : null sert de valeur temporaire pour les champs non transmis.
 * La liste des clés présentes est lue séparément : elle distingue absence et null explicite.
 */
#[OA\Schema(description: 'Modification partielle : envoyer uniquement les champs à changer')]
final class PatchProductInput
{
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(min: 2, max: 150)]
    #[OA\Property(nullable: false, example: 'Nouveau nom')]
    public ?string $name = null;

    #[Assert\Length(max: 1000)]
    #[OA\Property(nullable: true, example: 'Nouvelle description ou null')]
    public ?string $description = null;

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Regex(pattern: '/^\d{1,8}(?:\.\d{1,2})?$/')]
    #[OA\Property(type: 'string', nullable: false, example: '19.90')]
    public ?string $price = null;

    #[Assert\PositiveOrZero]
    #[OA\Property(type: 'integer', nullable: false, minimum: 0, example: 7)]
    public ?int $stock = null;

    #[OA\Property(type: 'boolean', nullable: false, example: false)]
    public ?bool $isActive = null;
}
