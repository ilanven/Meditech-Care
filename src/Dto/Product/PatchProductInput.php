<?php

declare(strict_types=1);

namespace App\Dto\Product;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contrat PATCH : valeurs null par défaut = champs potentiellement absents.
 * Pour distinguer absence de null explicite, garder aussi les clés du JSON reçu.
 * Seule description accepte null comme mise à jour effective.
 */
#[OA\Schema(description: 'Modification partielle : seuls les champs fournis sont modifiés. Une description à null est effacée.')]
final class PatchProductInput
{
    #[Assert\NotBlank(allowNull: true, message: 'Le nom ne peut pas être vide.')]
    #[Assert\Length(min: 2, max: 150)]
    #[OA\Property(type: 'string', nullable: false, minLength: 2, maxLength: 150, example: 'Nouveau nom')]
    public ?string $name = null;

    #[Assert\Length(max: 1000)]
    #[OA\Property(type: 'string', nullable: true, maxLength: 1000, example: 'Description modifiée')]
    public ?string $description = null;

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Regex(
        pattern: '/^\d{1,8}(?:\.\d{1,2})?$/',
        message: 'Le prix doit être positif ou nul, avec au maximum deux décimales.',
    )]
    #[OA\Property(type: 'string', nullable: false, pattern: '^\d{1,8}(\.\d{1,2})?$', example: '19.90')]
    public ?string $price = null;

    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    #[OA\Property(type: 'integer', nullable: false, minimum: 0, example: 7)]
    public ?int $stock = null;

    #[OA\Property(type: 'boolean', nullable: false, example: false)]
    public ?bool $isActive = null;
}
