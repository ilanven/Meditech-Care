<?php

declare(strict_types=1);

namespace App\Dto\Product;

use App\Entity\Product;
use OpenApi\Attributes as OA;

/** Réponse publique stable : aucun objet Doctrine ni champ interne exposé. */
#[OA\Schema(description: 'Produit exposé par l’API', required: ['id', 'name', 'description', 'price', 'stock', 'isActive'])]
final readonly class ProductOutput
{
    public function __construct(
        #[OA\Property(type: 'integer', example: 1)] public int $id,
        #[OA\Property(example: 'Clavier mécanique')] public string $name,
        #[OA\Property(nullable: true, example: 'Clavier compact AZERTY')] public ?string $description,
        #[OA\Property(type: 'string', example: '49.90')] public string $price,
        #[OA\Property(type: 'integer', minimum: 0, example: 12)] public int $stock,
        #[OA\Property(type: 'boolean', example: true)] public bool $isActive,
    ) {
    }

    public static function fromEntity(Product $product): self
    {
        return new self(
            id: $product->getId(),
            name: $product->getName(),
            description: $product->getDescription(),
            price: $product->getPrice(),
            stock: $product->getStock(),
            isActive: $product->isActive(),
        );
    }
}
