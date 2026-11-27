<?php

declare(strict_types=1);

namespace App\Dto\Product;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/** Contrat d'entrée de POST : jamais d'id choisi par le client. */
#[OA\Schema(
    description: 'Création d’un produit. name, price et stock sont obligatoires. description vaut null et isActive vaut true si absents.',
    required: ['name', 'price', 'stock'],
)]
final class CreateProductInput
{
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, max: 150)]
    #[OA\Property(type: 'string', minLength: 2, maxLength: 150, example: 'Clavier mécanique')]
    public string $name = '';

    #[Assert\Length(max: 1000)]
    #[OA\Property(type: 'string', nullable: true, maxLength: 1000, example: 'Clavier compact AZERTY')]
    public ?string $description = null;

    // Un DECIMAL Doctrine se manipule comme une chaîne (éviter les arrondis du float).
    #[Assert\NotBlank(message: 'Le prix est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^\d{1,8}(?:\.\d{1,2})?$/',
        message: 'Le prix doit être positif ou nul, avec au maximum deux décimales.',
    )]
    #[OA\Property(type: 'string', nullable: false, pattern: '^\d{1,8}(\.\d{1,2})?$', example: '49.90', description: 'Nombre décimal fourni comme chaîne, séparateur point')]
    public ?string $price = null;

    #[Assert\NotNull(message: 'Le stock est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    #[OA\Property(type: 'integer', nullable: false, minimum: 0, example: 12)]
    public ?int $stock = null;

    #[OA\Property(type: 'boolean', default: true, example: true)]
    public bool $isActive = true;
}
