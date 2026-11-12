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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * VERSION DE RÉFÉRENCE — les trois niveaux de ProductController sont acquis.
 *
 * HTTP : contrôleur ; lecture : Repository ; entrée : DTO + Serializer +
 * Validator ; écriture : ProductWriter ; sortie : ProductOutput ; contrat : OA.
 *
 * Le contrôle d'entrée explicite dans readInput() est un CHOIX pédagogique :
 * contrairement à MapRequestPayload (vu dans Verified), il fournit un format
 * d'erreur commun ET refuse strictement les clés et types inconnus.
 *
 * Pas d'authentification : démonstration locale, ne pas exposer en production.
 */
#[Route('/api/final/products', name: 'api_final_products_', format: 'json')]
final class ProductFinalController extends AbstractController
{
    /** GET collection — pas de JSON de requête, sortie exclusivement ProductOutput[]. */
    #[Route('', name: 'index', methods: ['GET'])]
    #[OA\Get(
        summary: 'Lister les produits (version finale)',
        description: 'Retourner tous les produits, triés par id croissant. Chaque entité Doctrine est transformée en ProductOutput : les propriétés internes de Product ne sont pas exposées automatiquement. La collection peut être vide, mais le statut reste 200 et le body est alors [].',
        tags: ['Products / 4 — Final'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tableau JSON de ProductOutput, éventuellement vide.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: ProductOutput::class)),
                    example: [[
                        'id' => 1, 'name' => 'Clavier mécanique', 'description' => 'AZERTY compact',
                        'price' => '49.90', 'stock' => 12, 'isActive' => true,
                    ]],
                ),
            ),
        ],
    )]
    public function index(ProductRepository $repository): JsonResponse
    {
        $products = $repository->findBy([], ['id' => 'ASC']);
        return $this->json(array_map(ProductOutput::fromEntity(...), $products));
    }

    /** GET par id — 404 si l'entité n'existe pas, sans confondre route et BDD. */
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        summary: 'Consulter un produit (version finale)',
        description: 'Chercher un produit par son identifiant. La route exige des chiffres (requirements) ; le repository vérifie ensuite l’existence de la ligne. Une ressource trouvée est projetée vers ProductOutput. Une ressource absente reçoit une erreur JSON structurée PRODUCT_NOT_FOUND.',
        tags: ['Products / 4 — Final'],
        parameters: [new OA\Parameter(
            name: 'id', in: 'path', required: true,
            description: 'Identifiant positif d’un produit existant, récupérable via GET collection.',
            schema: new OA\Schema(type: 'integer', minimum: 1), example: 1,
        )],
        responses: [
            new OA\Response(response: 200, description: 'Objet ProductOutput correspondant à cet id.', content: new OA\JsonContent(ref: new Model(type: ProductOutput::class))),
            new OA\Response(
                response: 404, description: 'Aucune ligne ne correspond à cet id.',
                content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class), example: [
                    'code' => 'PRODUCT_NOT_FOUND', 'message' => 'Produit introuvable.', 'details' => [],
                ]),
            ),
        ],
    )]
    public function show(int $id, ProductRepository $repository): JsonResponse
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->error('PRODUCT_NOT_FOUND', 'Produit introuvable.', 404);
        }
        return $this->json(ProductOutput::fromEntity($product));
    }

    /** POST — créer, valider, persister, puis retourner 201 + Location + DTO. */
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Créer un produit (version finale)',
        description: 'Créer une nouvelle ressource. Exiger Content-Type: application/json et un objet JSON. Refuser les clés inconnues, les types JSON incorrects et les valeurs violant Assert. L’identifiant est attribué par la base et ne doit jamais être fourni dans le body. Après validation, ProductWriter crée l’entité. Réponse 201 avec représentation ProductOutput et en-tête Location pointant vers son GET.',
        tags: ['Products / 4 — Final'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'name, price (chaîne décimale) et stock (entier) obligatoires ; description facultative, isActive vaut true par défaut.',
            content: new OA\JsonContent(
                ref: new Model(type: CreateProductInput::class),
                example: ['name' => 'Lampe USB', 'description' => 'Pour bureau', 'price' => '18.90', 'stock' => 5, 'isActive' => true],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201, description: 'Produit créé. L’en-tête Location indique son URL de lecture.',
                headers: [new OA\Header(header: 'Location', description: 'Chemin du GET unitaire de la ressource créée', schema: new OA\Schema(type: 'string', example: '/api/final/products/42'))],
                content: new OA\JsonContent(ref: new Model(type: ProductOutput::class)),
            ),
            new OA\Response(response: 400, description: 'JSON invalide, non objet ou valeurs JSON de types incorrects.', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(response: 415, description: 'Mauvais Content-Type (utiliser application/json).', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(
                response: 422, description: 'Champ requis absent, inconnu ou violation de contraintes (ex. stock négatif).',
                content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class), example: [
                    'code' => 'VALIDATION_FAILED', 'message' => 'Les données ne respectent pas les contraintes.',
                    'details' => [['field' => 'stock', 'message' => 'Le stock ne peut pas être négatif.']],
                ]),
            ),
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
        return $this->json(ProductOutput::fromEntity($product), 201, ['Location' => $location]);
    }

    /** PUT — même ressource, nouvel état complet des champs modifiables. */
    #[Route('/{id}', name: 'replace', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[OA\Put(
        summary: 'Remplacer un produit (version finale)',
        description: 'Remplacer intégralement les champs modifiables d’un produit existant, sans changer son id. name, price, stock et isActive sont obligatoires. Si description est omise, elle devient null : c’est un remplacement, pas une modification partielle. Le serveur ne crée pas un produit si l’id est absent : il répond 404. Les valeurs sont contrôlées avant toute écriture.',
        tags: ['Products / 4 — Final'],
        parameters: [new OA\Parameter(
            name: 'id', in: 'path', required: true, description: 'Identifiant du produit à remplacer, déjà présent dans la base.',
            schema: new OA\Schema(type: 'integer', minimum: 1), example: 1,
        )],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'État complet. Omettre description revient à la vider.',
            content: new OA\JsonContent(
                ref: new Model(type: ReplaceProductInput::class),
                example: ['name' => 'Lampe blanche', 'description' => null, 'price' => '19.90', 'stock' => 7, 'isActive' => true],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Produit remplacé ; corps ProductOutput à jour.', content: new OA\JsonContent(ref: new Model(type: ProductOutput::class))),
            new OA\Response(response: 400, description: 'JSON invalide, objet attendu ou types JSON incorrects.', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(response: 404, description: 'Produit inexistant.', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(response: 415, description: 'Mauvais Content-Type.', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(response: 422, description: 'Champs absents ou inconnus, ou valeurs invalides.', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
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
            return $this->error('PRODUCT_NOT_FOUND', 'Produit introuvable.', 404);
        }
        $input = $this->readInput($request, ReplaceProductInput::class, $serializer, $validator);
        if ($input instanceof JsonResponse) {
            return $input;
        }
        assert($input instanceof ReplaceProductInput);

        return $this->json(ProductOutput::fromEntity($writer->replace($product, $input)));
    }

    /** PATCH — appliquer uniquement les champs réellement présents dans le JSON. */
    #[Route('/{id}', name: 'patch', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Modifier partiellement un produit (version finale)',
        description: 'Envoyer un objet JSON non vide contenant seulement les champs à changer. Un champ absent conserve sa valeur ; description:null efface la description. En revanche, name:null, price:null, stock:null et isActive:null sont refusés. Le contrôleur conserve les noms des clés présentes puis les fournit au service ProductWriter. Les contraintes Assert sont appliquées aux nouvelles valeurs.',
        tags: ['Products / 4 — Final'],
        parameters: [new OA\Parameter(
            name: 'id', in: 'path', required: true, description: 'Identifiant du produit à modifier.',
            schema: new OA\Schema(type: 'integer', minimum: 1), example: 1,
        )],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Exemple : changer uniquement stock. Pour effacer la description, envoyer {"description":null}.',
            content: new OA\JsonContent(ref: new Model(type: PatchProductInput::class), example: ['stock' => 4]),
        ),
        responses: [
            new OA\Response(response: 200, description: 'ProductOutput avec les champs modifiés.', content: new OA\JsonContent(ref: new Model(type: ProductOutput::class))),
            new OA\Response(response: 400, description: 'JSON malformé ou types de valeurs invalides.', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(response: 404, description: 'Produit introuvable.', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(response: 415, description: 'Content-Type non pris en charge.', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
            new OA\Response(
                response: 422, description: 'Objet vide, champ inconnu, null interdit ou violation Assert.',
                content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class), example: [
                    'code' => 'NULL_NOT_ALLOWED', 'message' => 'Le champ name ne peut pas être null.',
                    'details' => [['field' => 'name', 'message' => 'Valeur null interdite.']],
                ]),
            ),
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
            return $this->error('PRODUCT_NOT_FOUND', 'Produit introuvable.', 404);
        }
        $input = $this->readInput($request, PatchProductInput::class, $serializer, $validator);
        if ($input instanceof JsonResponse) {
            return $input;
        }
        assert($input instanceof PatchProductInput);

        // readInput a déjà vérifié qu'il s'agit d'un objet JSON valide.
        /** @var array<string, mixed> $data */
        $data = (array) json_decode($request->getContent());

        return $this->json(ProductOutput::fromEntity($writer->patch($product, $input, array_keys($data))));
    }

    /** DELETE — 204 et pas de body, ou 404 avec ApiErrorOutput. */
    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Supprimer un produit (version finale)',
        description: 'Supprimer définitivement une ressource existante. ProductWriter délègue remove() et flush() à Doctrine. Une suppression réussie retourne 204 No Content, sans JSON ni représentation du produit. Un id absent reçoit une erreur JSON 404. Répéter DELETE sur cet id donnera donc 404 dans cette convention.',
        tags: ['Products / 4 — Final'],
        parameters: [new OA\Parameter(
            name: 'id', in: 'path', required: true, description: 'Identifiant à supprimer.',
            schema: new OA\Schema(type: 'integer', minimum: 1), example: 1,
        )],
        responses: [
            new OA\Response(response: 204, description: 'Supprimé. Body strictement absent.'),
            new OA\Response(response: 404, description: 'Aucun produit correspondant.', content: new OA\JsonContent(ref: new Model(type: ApiErrorOutput::class))),
        ],
    )]
    public function delete(int $id, ProductRepository $repository, ProductWriter $writer): Response
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->error('PRODUCT_NOT_FOUND', 'Produit introuvable.', 404);
        }
        $writer->delete($product);
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Point d'entrée commun à POST / PUT / PATCH.
     *
     * Le contrat HTTP est vérifié AVANT d'appeler le Serializer :
     * 1. Content-Type JSON et racine objet ; 2. champs autorisés / obligatoires ;
     * 3. types JSON exacts ; 4. désérialisation ; 5. contraintes Assert.
     *
     * L'ordre explique pourquoi une entrée malformée reçoit 400, un média
     * incorrect 415, et une valeur bien formée mais invalide 422.
     *
     * @param class-string<CreateProductInput|ReplaceProductInput|PatchProductInput> $type
     * @return CreateProductInput|ReplaceProductInput|PatchProductInput|JsonResponse
     */
    private function readInput(
        Request $request,
        string $type,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): CreateProductInput|ReplaceProductInput|PatchProductInput|JsonResponse {
        if ($request->getContentTypeFormat() !== 'json') {
            return $this->error('UNSUPPORTED_MEDIA_TYPE', 'Utiliser Content-Type: application/json.', 415);
        }

        try {
            $decoded = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->error('INVALID_JSON', 'Le corps doit contenir un JSON valide.', 400);
        }
        if (!$decoded instanceof \stdClass) {
            return $this->error('INVALID_JSON_OBJECT', 'Le corps doit contenir un objet JSON (et non une liste).', 400);
        }

        /** @var array<string, mixed> $data */
        $data = (array) $decoded;
        $allowed = ['name', 'description', 'price', 'stock', 'isActive'];
        $unknown = array_values(array_diff(array_keys($data), $allowed));
        if ($unknown !== []) {
            $details = [];
            foreach ($unknown as $field) {
                $details[] = ['field' => $field, 'message' => 'Champ non autorisé.'];
            }
            return $this->error('UNKNOWN_FIELDS', 'Le corps contient des champs inconnus.', 422, $details);
        }

        $required = match ($type) {
            CreateProductInput::class => ['name', 'price', 'stock'],
            ReplaceProductInput::class => ['name', 'price', 'stock', 'isActive'],
            PatchProductInput::class => [],
        };
        $missing = array_values(array_diff($required, array_keys($data)));
        if ($missing !== []) {
            $details = [];
            foreach ($missing as $field) {
                $details[] = ['field' => $field, 'message' => 'Champ obligatoire.'];
            }
            return $this->error('MISSING_FIELDS', 'Des champs obligatoires sont absents.', 422, $details);
        }
        if ($type === PatchProductInput::class && $data === []) {
            return $this->error('EMPTY_PATCH', 'Envoyer au moins un champ.', 422);
        }
        if ($type === PatchProductInput::class) {
            foreach (['name', 'price', 'stock', 'isActive'] as $field) {
                if (array_key_exists($field, $data) && $data[$field] === null) {
                    return $this->error(
                        'NULL_NOT_ALLOWED', "Le champ $field ne peut pas être null.", 422,
                        [['field' => $field, 'message' => 'Valeur null interdite.']],
                    );
                }
            }
        }

        // Contrôle explicite des types du JSON, avant les conversions du
        // Serializer : "12" n'est PAS un entier JSON, 12.5 non plus.
        $badTypes = [];
        foreach ($data as $field => $value) {
            $valid = match ($field) {
                'name', 'price' => is_string($value),
                'description' => is_string($value) || $value === null,
                'stock' => is_int($value),
                'isActive' => is_bool($value),
                default => false,
            };
            if (!$valid) {
                $badTypes[] = ['field' => $field, 'message' => 'Type JSON incorrect.'];
            }
        }
        if ($badTypes !== []) {
            return $this->error('INVALID_TYPES', 'Un ou plusieurs champs ont un type JSON incorrect.', 400, $badTypes);
        }

        try {
            $input = $serializer->deserialize($request->getContent(), $type, 'json');
        } catch (SerializerExceptionInterface | \TypeError | \ValueError) {
            return $this->error('INVALID_TYPES', 'Impossible de convertir les données en DTO.', 400);
        }
        if (!$input instanceof CreateProductInput && !$input instanceof ReplaceProductInput && !$input instanceof PatchProductInput) {
            return $this->error('INVALID_TYPES', 'Le corps ne correspond pas au DTO attendu.', 400);
        }

        $violations = $validator->validate($input);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $violation) {
                $details[] = ['field' => $violation->getPropertyPath(), 'message' => $violation->getMessage()];
            }
            return $this->error('VALIDATION_FAILED', 'Les données ne respectent pas les contraintes.', 422, $details);
        }

        return $input;
    }

    /**
     * @param list<array{field: string, message: string}> $details
     */
    private function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return $this->json(new ApiErrorOutput($code, $message, $details), $status);
    }
}
