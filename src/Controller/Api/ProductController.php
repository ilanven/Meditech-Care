<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Product\CreateProductInput;
use App\Dto\Product\PatchProductInput;
use App\Dto\Product\ProductOutput;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\ProductWriter;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * ATELIER PÉDAGOGIQUE — ne pas utiliser tel quel en production.
 *
 * IMPORTANT : les premières méthodes sont VOLONTAIREMENT incomplètes :
 * pas de validation, pas de DTO, vérifications minimales. Leur intérêt est
 * précisément de voir POURQUOI nous ajoutons ensuite les abstractions.
 *
 * Routes séparées de la version finale pour permettre de comparer les deux
 * sur la même base de données, sans collision.
 */
#[Route('/api/learning/products', name: 'api_learning_products_')]
final class ProductController extends AbstractController
{
    /* ======================================================================
     * ÉTAPE 1 — GET COLLECTION : Repository + réponse JSON, rien de plus.
     * Objectif : voir le trajet HTTP -> Route -> Contrôleur -> Repository -> JSON.
     * Les tableaux sont assemblés manuellement. Aucun Serializer, DTO ni OA.
     * ====================================================================== */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ProductRepository $repository): JsonResponse
    {
        // Symfony injecte le repository dans les arguments de la méthode.
        // findAll() est fourni par le repository Doctrine : il retourne des entités Product.
        $products = $repository->findAll();

        // Ici, nous CHOISISSONS manuellement les champs renvoyés au client.
        $result = [];
        foreach ($products as $product) {
            $result[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'stock' => $product->getStock(),
            ];
        }

        // json() crée une JsonResponse : encodage JSON et Content-Type approprié.
        // Le statut 200 est implicite lorsque tout va bien.
        return $this->json($result);
    }

    /* ======================================================================
     * ÉTAPE 2 — GET PAR ID : paramètre de route et réponse 404.
     * Nouveauté : {id} est converti en argument PHP int, puis recherché en BDD.
     * Une ressource manquante n'est pas une erreur serveur 500.
     * ====================================================================== */
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, ProductRepository $repository): JsonResponse
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['message' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // La forme de la réponse est encore construite manuellement.
        return $this->json([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'isActive' => $product->isActive(),
        ]);
    }

    /* ======================================================================
     * ÉTAPE 3 — POST V1 : lire un body JSON et enregistrer dans Doctrine.
     * Nouveautés : Request::toArray(), setters, persist(), flush(), statut 201.
     * ATTENTION : on fait exprès de ne valider AUCUN champ. Une entrée malformée
     * peut provoquer une exception. Nous corrigerons cela plus loin.
     * ====================================================================== */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Le body JSON est décodé en tableau associatif PHP.
        // Exemple : {"name":"Clavier","price":"49.90","stock":12}
        $data = $request->toArray();

        $product = new Product();
        $product->setName($data['name']);
        $product->setDescription($data['description'] ?? null);
        $product->setPrice($data['price']);
        $product->setStock($data['stock']);
        $product->setIsActive($data['isActive'] ?? true);

        // persist() place l'entité sous la surveillance de l'EntityManager.
        // flush() exécute les écritures SQL et l'id devient disponible.
        $em->persist($product);
        $em->flush();

        // 201 Created signifie qu'une nouvelle ressource a été créée.
        return $this->json(['id' => $product->getId(), 'message' => 'Créé'], Response::HTTP_CREATED);
    }

    /* ======================================================================
     * ÉTAPE 4 — PUT V1 : remplacer tous les champs modifiables d'un produit.
     * Nouveauté : distinguer création (POST) et remplacement (PUT).
     * L'entité existe déjà : Doctrine suit ses changements, donc pas de persist().
     * Toujours pas de validation (limite pédagogique intentionnelle).
     * ====================================================================== */
    #[Route('/{id}', name: 'replace', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function replace(int $id, Request $request, ProductRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['message' => 'Produit introuvable.'], 404);
        }
        $data = $request->toArray();
        $product->setName($data['name']);
        $product->setDescription($data['description'] ?? null);
        $product->setPrice($data['price']);
        $product->setStock($data['stock']);
        $product->setIsActive($data['isActive']);

        // Pas de persist() : $product est une entité gérée récupérée par Doctrine.
        $em->flush();
        return $this->json(['id' => $product->getId(), 'message' => 'Remplacé']);
    }

    /* ======================================================================
     * ÉTAPE 5 — DELETE : supprimer une entité et répondre sans contenu.
     * Nouveautés : remove(), flush() et HTTP 204 No Content.
     * Une réponse 204 ne doit pas renvoyer de body JSON.
     * ====================================================================== */
    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, ProductRepository $repository, EntityManagerInterface $em): Response
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['message' => 'Produit introuvable.'], 404);
        }
        $em->remove($product);
        $em->flush();
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /* ======================================================================
     * ÉTAPE 6 — GET V2 : découvrir le Serializer et les groupes.
     * Avant : nous fabriquions le tableau JSON à la main.
     * Maintenant : Symfony transforme des entités en JSON, mais SEULEMENT
     * les propriétés marquées #[Groups(['product:read'])] dans Product.php.
     * L'avantage est pratique ; l'inconvénient est de coupler l'API à l'entité.
     * ====================================================================== */
    #[Route('/demo/serializer', name: 'serializer', methods: ['GET'])]
    public function serializerGroup(ProductRepository $repository): JsonResponse
    {
        $products = $repository->findAll();
        // 4e argument = contexte de sérialisation ; il limite les champs exposés.
        return $this->json($products, Response::HTTP_OK, [], ['groups' => ['product:read']]);
    }

    /* ======================================================================
     * ÉTAPE 7 — GET V3 : DTO de sortie (Data Transfer Object).
     * Problème de l'étape 6 : le contrat JSON dépend toujours de l'entité.
     * Solution : ProductOutput est une classe ne contenant QUE les champs
     * que l'API décide d'exposer. La méthode fromEntity() effectue le mapping.
     * ====================================================================== */
    #[Route('/demo/output-dto', name: 'output_dto', methods: ['GET'])]
    public function outputDto(ProductRepository $repository): JsonResponse
    {
        $outputs = [];
        foreach ($repository->findAll() as $product) {
            // Séparation : Product (persistance) -> ProductOutput (contrat HTTP).
            $outputs[] = ProductOutput::fromEntity($product);
        }
        return $this->json($outputs);
    }

    /* ======================================================================
     * ÉTAPE 8 — POST V2 : DTO d'entrée + Serializer, SANS validation.
     * Avant : $data['name'] etc. partout. Maintenant : désérialiser le JSON
     * dans un objet CreateProductInput qui n'a ni id ni propriétés Doctrine.
     * C'est une liste blanche de champs : le client ne choisit pas l'id.
     * Limite : un DTO n'est pas, à lui seul, une preuve de validité !
     * ====================================================================== */
    #[Route('/demo/input-dto', name: 'input_dto', methods: ['POST'])]
    public function createWithInputDto(Request $request, SerializerInterface $serializer, ProductWriter $writer): JsonResponse
    {
        try {
            // Le Serializer convertit le JSON en objet ; il ne vérifie PAS les Assert ici.
            $input = $serializer->deserialize($request->getContent(), CreateProductInput::class, 'json');
        } catch (SerializerExceptionInterface | \TypeError) {
            return $this->json(['message' => 'JSON ou types invalides.'], 400);
        }

        // Avec un prix/stock absent, cette étape peut encore échouer : c'est voulu.
        $product = $writer->create($input);
        return $this->json(ProductOutput::fromEntity($product), 201);
    }

    /* ======================================================================
     * ÉTAPE 9 — POST V3 : Validator et attributs Assert.
     * Nouveauté : tester les contraintes définies sur CreateProductInput
     * (NotBlank, Length, Regex, PositiveOrZero...) AVANT d'enregistrer.
     * Les violations sont transformées en réponse JSON 422 lisible.
     * ====================================================================== */
    #[Route('/demo/validated', name: 'validated', methods: ['POST'])]
    public function createValidated(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ProductWriter $writer,
    ): JsonResponse {
        try {
            $input = $serializer->deserialize($request->getContent(), CreateProductInput::class, 'json');
        } catch (SerializerExceptionInterface | \TypeError) {
            return $this->json(['message' => 'JSON ou types invalides.'], 400);
        }

        // Le Validator inspecte les #[Assert\...] déclarés dans le DTO.
        $violations = $validator->validate($input);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }
            return $this->json(['code' => 'VALIDATION_FAILED', 'details' => $errors], 422);
        }

        return $this->json(ProductOutput::fromEntity($writer->create($input)), 201);
    }

    /* ======================================================================
     * ÉTAPE 10 — POST V4 : #[MapRequestPayload].
     * Symfony effectue à notre place : décodage du body, dénormalisation
     * en CreateProductInput et validation via Assert.
     * Le contrôleur devient plus court ; par défaut, Symfony renvoie 422
     * en cas de violation. Le format d'erreur est alors celui de Symfony.
     * ====================================================================== */
    #[Route('/demo/map-payload', name: 'map_payload', methods: ['POST'])]
    public function createMapped(
        #[MapRequestPayload(acceptFormat: 'json')] CreateProductInput $input,
        ProductWriter $writer,
    ): JsonResponse {
        return $this->json(ProductOutput::fromEntity($writer->create($input)), 201);
    }

    /* ======================================================================
     * ÉTAPE 11 — PATCH : champ absent ≠ champ envoyé à null.
     * Contrairement à PUT, PATCH ne touche qu'aux champs présents.
     * On conserve array_keys($data) en complément du PatchProductInput.
     * Seule la description est nullable dans notre contrat.
     * ====================================================================== */
    #[Route('/demo/{id}/patch', name: 'patch_demo', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function patchDemo(
        int $id,
        Request $request,
        ProductRepository $repository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ProductWriter $writer,
    ): JsonResponse {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['code' => 'PRODUCT_NOT_FOUND'], 404);
        }
        try {
            $data = $request->toArray();
            $input = $serializer->deserialize($request->getContent(), PatchProductInput::class, 'json');
        } catch (\Exception | \TypeError) {
            return $this->json(['code' => 'INVALID_JSON'], 400);
        }

        $allowed = ['name', 'description', 'price', 'stock', 'isActive'];
        if ($data === [] || array_diff(array_keys($data), $allowed) !== []) {
            return $this->json(['code' => 'INVALID_FIELDS'], 422);
        }
        foreach (['name', 'price', 'stock', 'isActive'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === null) {
                return $this->json(['code' => 'NULL_NOT_ALLOWED', 'field' => $key], 422);
            }
        }
        $violations = $validator->validate($input);
        if (count($violations) > 0) {
            return $this->json(['code' => 'VALIDATION_FAILED'], 422);
        }

        // Si description est absente, le service ne la modifie pas.
        // Si "description": null est présent, le service la remet à null.
        $writer->patch($product, $input, array_keys($data));
        return $this->json(ProductOutput::fromEntity($product));
    }

    /* ======================================================================
     * ÉTAPE 12 — GET AVEC QUERY PARAMETER : filtrer au niveau de Doctrine.
     * /demo/filter?active=1 et ?active=0 ; en absence de filtre : tous.
     * Le filtre est borné à des valeurs connues : pas de SQL généré par le client.
     * ====================================================================== */
    #[Route('/demo/filter', name: 'filter', methods: ['GET'])]
    public function filter(Request $request, ProductRepository $repository): JsonResponse
    {
        $active = $request->query->get('active'); // '1', '0' ou null, jamais un bool.
        if ($active !== null && !in_array($active, ['0', '1'], true)) {
            return $this->json(['code' => 'INVALID_FILTER', 'message' => 'active doit valoir 0 ou 1.'], 400);
        }
        // findBy() effectue le WHERE directement en base.
        $criteria = $active === null ? [] : ['isActive' => $active === '1'];
        $products = $repository->findBy($criteria, ['id' => 'ASC']);
        return $this->json(array_map(ProductOutput::fromEntity(...), $products));
    }

    /* ======================================================================
     * ÉTAPE 13 — POST V5 : ajouter un contrat OpenAPI lisible par Swagger UI.
     * La création ressemble à l'étape 10, MAIS ses entrées, sorties et codes
     * sont formalisés par #[OA\Post], #[OA\RequestBody], #[OA\Response].
     * Nelmio produit le document OpenAPI ; Swagger UI l'affiche et l'exécute.
     * Un DTO référencé avec Model évite de recopier tous ses champs ici.
     * ====================================================================== */
    #[Route('/demo/documented', name: 'documented', methods: ['POST'])]
    #[OA\Post(
        summary: 'Créer un produit (démonstration de la documentation OpenAPI)',
        description: 'Même traitement que MapRequestPayload, mais entrée et réponses décrites pour Swagger UI.',
        tags: ['Products - Apprentissage'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: CreateProductInput::class)),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Produit créé', content: new OA\JsonContent(ref: new Model(type: ProductOutput::class))),
            new OA\Response(response: 400, description: 'Body JSON malformé ou valeurs de types incorrects'),
            new OA\Response(response: 415, description: 'Format non pris en charge'),
            new OA\Response(response: 422, description: 'Contraintes de validation non respectées (format Symfony)'),
        ],
    )]
    public function createDocumented(
        #[MapRequestPayload(acceptFormat: 'json')] CreateProductInput $input,
        ProductWriter $writer,
    ): JsonResponse {
        return $this->json(ProductOutput::fromEntity($writer->create($input)), Response::HTTP_CREATED);
    }
}
