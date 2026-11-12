<?php

declare(strict_types=1);

namespace App\Dto\Product;

use OpenApi\Attributes as OA;

/** Format commun des erreurs que construit explicitement ProductFinalController. */
#[OA\Schema(description: 'Erreur d’API avec code stable, message lisible et détails par champ', required: ['code', 'message', 'details'])]
final readonly class ApiErrorOutput
{
    #[OA\Property(type: 'string', example: 'VALIDATION_FAILED')]
    public string $code;

    #[OA\Property(type: 'string', example: 'Les données ne respectent pas les contraintes.')]
    public string $message;

    /** @var list<array{field: string, message: string}> */
    #[OA\Property(
        type: 'array',
        description: 'Liste de problèmes associés aux champs, vide pour une erreur globale',
        items: new OA\Items(
            type: 'object',
            required: ['field', 'message'],
            properties: [
                new OA\Property(property: 'field', type: 'string', example: 'stock'),
                new OA\Property(property: 'message', type: 'string', example: 'Le stock ne peut pas être négatif.'),
            ],
        ),
    )]
    public array $details;

    /**
     * @param list<array{field: string, message: string}> $details
     */
    public function __construct(string $code, string $message, array $details = [])
    {
        $this->code = $code;
        $this->message = $message;
        $this->details = $details;
    }
}
