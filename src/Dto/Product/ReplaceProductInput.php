<?php

declare(strict_types=1);

namespace App\Dto\Product;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/** Contrat PUT : remplacement des champs modifiables d'une ressource existante. */
#[OA\Schema(
    description: 'Remplacement complet : name, price, stock et isActive requis. description omise est remise à null.',
    required: ['name', 'price', 'stock', 'isActive'],
)]
final class ReplaceProductInput
{
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, max: 150)]
    #[OA\Property(type: 'string', minLength: 2, maxLength: 150, example: 'Souris ergonomique')]
    public string $name = '';

    #[Assert\Length(max: 1000)]
    #[OA\Property(type: 'string', nullable: true, maxLength: 1000, example: 'Modèle sans fil')]
    public ?string $description = null;

    #[Assert\NotBlank(message: 'Le prix est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^\d{1,8}(?:\.\d{1,2})?$/',
        message: 'Le prix doit être positif ou nul, avec au maximum deux décimales.',
    )]
    #[OA\Property(type: 'string', nullable: false, pattern: '^\d{1,8}(\.\d{1,2})?$', example: '29.90')]
    public ?string $price = null;

    #[Assert\NotNull(message: 'Le stock est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    #[OA\Property(type: 'integer', nullable: false, minimum: 0, example: 10)]
    public ?int $stock = null;

    #[Assert\NotNull(message: 'Le statut actif est obligatoire.')]
    #[OA\Property(type: 'boolean', nullable: false, example: true)]
    public ?bool $isActive = null;
}
