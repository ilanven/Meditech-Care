<?php

declare(strict_types=1);

namespace App\Dto\Product;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/** PUT remplace l'état modifiable : tous les champs non-nullables sont requis. */
#[OA\Schema(description: 'Données de remplacement complet', required: ['name', 'price', 'stock', 'isActive'])]
final class ReplaceProductInput
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 150)]
    #[OA\Property(example: 'Souris ergonomique')]
    public string $name = '';

    #[Assert\Length(max: 1000)]
    #[OA\Property(nullable: true, example: 'Modèle sans fil')]
    public ?string $description = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,8}(?:\.\d{1,2})?$/')]
    #[OA\Property(type: 'string', example: '29.90')]
    public ?string $price = null;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    #[OA\Property(type: 'integer', minimum: 0, example: 10)]
    public ?int $stock = null;

    #[Assert\NotNull]
    #[OA\Property(type: 'boolean', example: true)]
    public ?bool $isActive = null;
}
