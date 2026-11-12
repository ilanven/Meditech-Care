<?php

declare(strict_types=1);

namespace App\Dto\Product;

use App\Entity\Product;
use OpenApi\Attributes as OA;

/** DTO de sortie : la forme JSON ne dépend plus directement de l'entité Doctrine. */
#[OA\Schema(description: 'Représentation publique d’un produit', required: ['id', 'name', 'description', 'price', 'stock', 'isActive'])]
final readonly class ProductOutput
{
    public function __construct(
        #[OA\Property(type: 'integer', example: 1)] public int $id,
        #[OA\Property(type: 'string', example: 'Clavier mécanique')] public string $name,
        #[OA\Property(type: 'string', nullable: true, example: 'Clavier compact AZERTY')] public ?string $description,
        #[OA\Property(type: 'string', example: '49.90')] public string $price,
        #[OA\Property(type: 'integer', minimum: 0, example: 12)] public int $stock,
        #[OA\Property(type: 'boolean', example: true)] public bool $isActive,
    ) {
    }

    public static function fromEntity(Product $product): self
    {
        $id = $product->getId();
        $name = $product->getName();
        $price = $product->getPrice();
        $stock = $product->getStock();

        // Doctrine déclare ces propriétés nullable durant la vie d'une entité
        // neuve. Pour une réponse publique, elles doivent avoir une vraie valeur.
        if ($id === null || $name === null || $price === null || $stock === null) {
            throw new \LogicException('Impossible de publier un produit incomplet.');
        }

        return new self($id, $name, $product->getDescription(), $price, $stock, $product->isActive());
    }
}
