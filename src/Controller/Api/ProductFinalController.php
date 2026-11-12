<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Product\ApiErrorOutput;
use App\Dto\Product\CreateProductInput;
use App\Dto\Product\PatchProductInput;
use App\Dto\Product\ProductOutput;
use App\Dto\Product\ReplaceProductInput;
use App\Repository\ProductRepository;
use App\Service\ProductWriter;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Version de référence : DTO explicites, validation contrôlée, service d'écriture,
 * codes HTTP cohérents et contrat OpenAPI. Pas d'authentification dans cet atelier.
 */
#[Route('/api/final/products', name: 'api_final_products_')]
final class ProductFinalController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    #[OA\Get(
        summary: 'Lister les produits',
        tags: ['Products - Final'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des produits',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: ProductOutput::class))),
            ),
        ],
    )]
    public function index(ProductRepository $repository): JsonResponse
    {
        $products = $repository->findBy([], ['id' => 'ASC']);
        return $this->json(array_map(ProductOutput::fromEntity(...), $products));
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        summary: 'Consulter un produit',
        tags: ['Products - Final'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Produit trouvé', content: new OA\JsonContent(ref: new Model(type: ProductOutput::class))),
            new OA\Response(response: 404, description: 'Produit introuvable', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
        ],
    )]
    public function show(int $id, ProductRepository $repository): JsonResponse
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->error('PRODUCT_NOT_FOUND', 'Produit introuvable.', Response::HTTP_NOT_FOUND);
        }
        return $this->json(ProductOutput::fromEntity($product));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Créer un produit',
        tags: ['Products - Final'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CreateProductInput::class))),
        responses: [
            new OA\Response(response: 201, description: 'Produit créé', content: new OA\JsonContent(ref: new Model(type: ProductOutput::class))),
            new OA\Response(response: 400, description: 'JSON invalide ou type incorrect', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(response: 415, description: 'Content-Type non pris en charge', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(response: 422, description: 'Validation échouée', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
        ],
    )]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ProductWriter $writer,
    ): JsonResponse {
        $input = $this->readInput($request, CreateProductInput::class, $serializer, $validator);
        if ($input instanceof JsonResponse) {
            return $input;
        }
        assert($input instanceof CreateProductInput);

        $product = $writer->create($input);
        $location = $this->generateUrl('api_final_products_show', ['id' => $product->getId()]);
        return $this->json(ProductOutput::fromEntity($product), Response::HTTP_CREATED, ['Location' => $location]);
    }

    #[Route('/{id}', name: 'replace', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[OA\Put(
        summary: 'Remplacer les champs modifiables d’un produit',
        tags: ['Products - Final'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: ReplaceProductInput::class))),
        responses: [
            new OA\Response(response: 200, description: 'Produit remplacé', content: new OA\JsonContent(ref: new Model(type: ProductOutput::class))),
            new OA\Response(response: 400, description: 'JSON invalide ou type incorrect', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(response: 404, description: 'Produit introuvable', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(response: 415, description: 'Content-Type non pris en charge', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(response: 422, description: 'Validation échouée', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
        ],
    )]
    public function replace(
        int $id,
        Request $request,
        ProductRepository $repository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ProductWriter $writer,
    ): JsonResponse {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->error('PRODUCT_NOT_FOUND', 'Produit introuvable.', Response::HTTP_NOT_FOUND);
        }
        $input = $this->readInput($request, ReplaceProductInput::class, $serializer, $validator);
        if ($input instanceof JsonResponse) {
            return $input;
        }
        assert($input instanceof ReplaceProductInput);

        return $this->json(ProductOutput::fromEntity($writer->replace($product, $input)));
    }

    #[Route('/{id}', name: 'patch', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Modifier seulement les champs fournis',
        description: 'Le champ description accepte explicitement null. Pour les autres champs, null est refusé. Un objet vide est refusé.',
        tags: ['Products - Final'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: PatchProductInput::class))),
        responses: [
            new OA\Response(response: 200, description: 'Produit modifié', content: new OA\JsonContent(ref: new Model(type: ProductOutput::class))),
            new OA\Response(response: 400, description: 'JSON invalide ou type incorrect', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(response: 404, description: 'Produit introuvable', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(response: 415, description: 'Content-Type non pris en charge', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(response: 422, description: 'Validation échouée', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
        ],
    )]
    public function patch(
        int $id,
        Request $request,
        ProductRepository $repository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ProductWriter $writer,
    ): JsonResponse {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->error('PRODUCT_NOT_FOUND', 'Produit introuvable.', Response::HTTP_NOT_FOUND);
        }

        // Conserver les clés réellement présentes : une propriété absente ≠ une propriété à null.
        $input = $this->readInput($request, PatchProductInput::class, $serializer, $validator);
        if ($input instanceof JsonResponse) {
            return $input;
        }
        assert($input instanceof PatchProductInput);
        $data = $request->toArray(); // Déjà validé comme JSON objet par readInput().
        if ($data === []) {
            return $this->error('EMPTY_PATCH', 'Envoyer au moins un champ.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        foreach (['name', 'price', 'stock', 'isActive'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === null) {
                return $this->error('NULL_NOT_ALLOWED', "Le champ $key ne peut pas être null.", Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        return $this->json(ProductOutput::fromEntity($writer->patch($product, $input, array_keys($data))));
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Supprimer un produit',
        tags: ['Products - Final'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))],
        responses: [
            new OA\Response(response: 204, description: 'Produit supprimé, réponse sans body'),
            new OA\Response(response: 404, description: 'Produit introuvable', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
        ],
    )]
    public function delete(int $id, ProductRepository $repository, ProductWriter $writer): Response
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->error('PRODUCT_NOT_FOUND', 'Produit introuvable.', Response::HTTP_NOT_FOUND);
        }
        $writer->delete($product);
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Entrée commune à POST/PUT/PATCH : vérifier format, clés, types et contraintes.
     * Choix explicite plutôt que MapRequestPayload pour garder UN format d'erreur ici.
     *
     * @param class-string<CreateProductInput|ReplaceProductInput|PatchProductInput> $type
     * @return CreateProductInput|ReplaceProductInput|PatchProductInput|JsonResponse
     */
    private function readInput(
        Request $request,
        string $type,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): object {
        if ($request->getContentTypeFormat() !== 'json') {
            return $this->error('UNSUPPORTED_MEDIA_TYPE', 'Utiliser Content-Type: application/json.', Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }
        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return $this->error('INVALID_JSON', 'Le body doit être un objet JSON valide.', Response::HTTP_BAD_REQUEST);
        }

        // Request::toArray() accepte un tableau JSON ; notre contrat exige un objet JSON.
        $decoded = json_decode($request->getContent());
        if (!$decoded instanceof \stdClass) {
            return $this->error('INVALID_JSON_OBJECT', 'Le body doit être un objet JSON.', Response::HTTP_BAD_REQUEST);
        }

        $allowed = ['name', 'description', 'price', 'stock', 'isActive'];
        $unknown = array_diff(array_keys($data), $allowed);
        if ($unknown !== []) {
            return $this->error(
                'UNKNOWN_FIELDS',
                'Le payload contient des champs non autorisés.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                array_map(static fn (string $field): array => ['field' => $field, 'message' => 'Champ non autorisé.'], array_values($unknown))
            );
        }
        // PUT exige tous les champs non-nullables. POST laisse isActive à true par défaut.
        $required = $type === ReplaceProductInput::class
            ? ['name', 'price', 'stock', 'isActive']
            : ($type === CreateProductInput::class ? ['name', 'price', 'stock'] : []);
        $missing = array_values(array_diff($required, array_keys($data)));
        if ($missing !== []) {
            return $this->error(
                'MISSING_FIELDS',
                'Des champs obligatoires sont absents.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                array_map(static fn (string $field): array => ['field' => $field, 'message' => 'Champ obligatoire.'], $missing)
            );
        }

        try {
            $input = $serializer->deserialize($request->getContent(), $type, 'json');
        } catch (SerializerExceptionInterface | \TypeError) {
            return $this->error('INVALID_TYPES', 'Un ou plusieurs champs ont un type incorrect.', Response::HTTP_BAD_REQUEST);
        }

        $violations = $validator->validate($input);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $violation) {
                $details[] = ['field' => $violation->getPropertyPath(), 'message' => $violation->getMessage()];
            }
            return $this->error('VALIDATION_FAILED', 'Les données ne respectent pas les contraintes.', Response::HTTP_UNPROCESSABLE_ENTITY, $details);
        }

        return $input;
    }

    private function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return $this->json(new ApiErrorOutput($code, $message, $details), $status);
    }
}
