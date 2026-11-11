<?php

declare(strict_types=1);

namespace App\Dto\Product;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/** Données acceptées par POST : aucun id envoyé par le client. */
#[OA\Schema(description: 'Données de création d’un produit', required: ['name', 'price', 'stock'])]
final class CreateProductInput
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 150)]
    #[OA\Property(example: 'Clavier mécanique', minLength: 2, maxLength: 150)]
    public string $name = '';

    #[Assert\Length(max: 1000)]
    #[OA\Property(nullable: true, example: 'Clavier compact AZERTY')]
    public ?string $description = null;

    // Prix volontairement en string (ex. "49.90") pour éviter les imprécisions du float.
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,8}(?:\.\d{1,2})?$/', message: 'Le prix doit être positif ou nul et avoir au maximum deux décimales.')]
    #[OA\Property(type: 'string', example: '49.90', description: 'Montant décimal en chaîne, avec un point comme séparateur')]
    public ?string $price = null;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    #[OA\Property(type: 'integer', minimum: 0, example: 12)]
    public ?int $stock = null;

    #[OA\Property(default: true, example: true)]
    public bool $isActive = true;
}
