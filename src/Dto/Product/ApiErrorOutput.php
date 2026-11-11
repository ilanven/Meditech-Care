<?php

declare(strict_types=1);

namespace App\Dto\Product;

use OpenApi\Attributes as OA;

/** Convention d'erreur commune aux réponses construites par le contrôleur final. */
#[OA\Schema(description: 'Erreur métier ou de validation', required: ['code', 'message', 'details'])]
final readonly class ApiErrorOutput
{
    public function __construct(
        #[OA\Property(example: 'PRODUCT_NOT_FOUND')] public string $code,
        #[OA\Property(example: 'Produit introuvable.')] public string $message,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'object'))] public array $details = [],
    ) {}
}
