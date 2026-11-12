<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Product\CreateProductInput;
use App\Dto\Product\PatchProductInput;
use App\Dto\Product\ProductOutput;
use App\Dto\Product\ReplaceProductInput;
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
 * LABORATOIRE : trois versions du même CRUD, volontairement côte à côte.
 *
 * 1. basic    : traiter le JSON à la main ; pas de validation volontairement.
 * 2. tagOA    : même logique métier, contrat OpenAPI développé.
 * 3. verified : DTO, Serializer, Validator et MapRequestPayload en pratique.
 *
 * Le code de ces trois blocs n'est PAS un modèle de production. Comparer
 * ensuite chaque méthode avec ProductFinalController et le guide Markdown.
 *
 * Les 18 méthodes partagent volontairement la même table product.
 */
#[Route('/api/learning/products', name: 'api_learning_products_', format: 'json')]

// ───────────────────── COMPOSANTS OPENAPI DU NIVEAU 2 ─────────────────────
// Ces schémas n'exécutent aucune vérification sur le serveur. Ce sont des
// descriptions réutilisables publiées dans components/schemas de l'OpenAPI.
#[OA\Schema(
    schema: 'LearningProduct',
    description: 'Produit visible dans le laboratoire : projection manuelle de l’entité Product.',
    type: 'object',
    required: ['id', 'name', 'description', 'price', 'stock', 'isActive'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', readOnly: true, example: 5, description: 'Identifiant créé par Doctrine'),
        new OA\Property(property: 'name', type: 'string', example: 'Clavier mécanique', description: 'Nom du produit'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Modèle AZERTY'),
        new OA\Property(property: 'price', type: 'string', example: '49.90', description: 'Prix en chaîne décimale (DECIMAL Doctrine)'),
        new OA\Property(property: 'stock', type: 'integer', example: 12),
        new OA\Property(property: 'isActive', type: 'boolean', example: true),
    ],
)]
#[OA\Schema(
    schema: 'LearningProductCreate',
    type: 'object',
    description: 'Corps de POST décrit par OpenAPI ; les règles ne sont pas validées automatiquement par TagOA.',
    required: ['name', 'price', 'stock'],
    properties: [
        new OA\Property(property: 'name', type: 'string', minLength: 2, maxLength: 150, example: 'Clavier mécanique'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'AZERTY compact'),
        new OA\Property(property: 'price', type: 'string', example: '49.90'),
        new OA\Property(property: 'stock', type: 'integer', minimum: 0, example: 12),
        new OA\Property(property: 'isActive', type: 'boolean', default: true, example: true),
    ],
)]
#[OA\Schema(
    schema: 'LearningProductReplace',
    type: 'object',
    description: 'Corps de PUT : l’état modifiable complet, description facultative (omise = null).',
    required: ['name', 'price', 'stock', 'isActive'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Nouveau clavier'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Sans fil'),
        new OA\Property(property: 'price', type: 'string', example: '59.90'),
        new OA\Property(property: 'stock', type: 'integer', example: 7),
        new OA\Property(property: 'isActive', type: 'boolean', example: true),
    ],
)]
#[OA\Schema(
    schema: 'LearningProductPatch',
    type: 'object',
    description: 'Corps partiel de PATCH ; toutes les propriétés sont facultatives, description accepte null.',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Nouveau nom'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Nouvelle description'),
        new OA\Property(property: 'price', type: 'string', example: '19.90'),
        new OA\Property(property: 'stock', type: 'integer', example: 4),
        new OA\Property(property: 'isActive', type: 'boolean', example: false),
    ],
)]
#[OA\Schema(
    schema: 'LearningProductNotFound',
    type: 'object',
    required: ['message'],
    properties: [new OA\Property(property: 'message', type: 'string', example: 'Produit introuvable.')],
)]
final class ProductController extends AbstractController
{
    // ======================================================================
    // NIVEAU 1 — BASIC : 6 opérations CRUD sans DTO ni Validator.
    // ======================================================================

    /**
     * BASIC 1/6 — GET collection.
     * Repository : trouver les entités ; foreach : choisir les champs visibles.
     * On construit manuellement la représentation JSON pour voir chaque étape.
     */
    #[Route('/basic', name: 'basic_index', methods: ['GET'])]
    #[OA\Get(summary: 'Basic — lister les produits', tags: ['Products / 1 — Basic'], responses: [new OA\Response(response: 200, description: 'Tableau JSON des produits')])]
    public function basicIndex(ProductRepository $repository): JsonResponse
    {
        $result = [];
        foreach ($repository->findBy([], ['id' => 'ASC']) as $product) {
            $result[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $product->getPrice(),
                'stock' => $product->getStock(),
                'isActive' => $product->isActive(),
            ];
        }

        // AbstractController::json() encode le tableau PHP en JSON (statut 200).
        return $this->json($result);
    }

    /**
     * BASIC 2/6 — GET unitaire.
     * {id} est extrait de l'URL ; \d+ signifie « un ou plusieurs chiffres ».
     * Attention : valider la forme de l'URL ne garantit pas l'existence en BDD.
     */
    #[Route('/basic/{id}', name: 'basic_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(summary: 'Basic — lire un produit', tags: ['Products / 1 — Basic'], responses: [new OA\Response(response: 200, description: 'Produit retrouvé'), new OA\Response(response: 404, description: 'Identifiant absent de la BDD')])]
    public function basicShow(int $id, ProductRepository $repository): JsonResponse
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['message' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'isActive' => $product->isActive(),
        ]);
    }

    /**
     * BASIC 3/6 — POST : JSON -> tableau PHP -> entité -> base.
     * Aucune validation : utiliser un JSON CORRECT pour ce premier atelier.
     * Le requestBody OpenAPI sert UNIQUEMENT à saisir du JSON dans Swagger.
     */
    #[Route('/basic', name: 'basic_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Basic — créer un produit',
        tags: ['Products / 1 — Basic'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'price', 'stock'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Lampe USB'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Pour bureau'),
                new OA\Property(property: 'price', type: 'string', example: '18.90'),
                new OA\Property(property: 'stock', type: 'integer', example: 5),
                new OA\Property(property: 'isActive', type: 'boolean', example: true),
            ],
            type: 'object',
        )),
        responses: [new OA\Response(response: 201, description: 'Produit créé ; JSON contenant son id')],
    )]
    public function basicCreate(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // toArray() décode le body JSON, mais NE valide PAS les règles métier.
        $data = $request->toArray();

        $product = new Product();
        $product->setName($data['name']);
        $product->setDescription($data['description'] ?? null);
        $product->setPrice($data['price']);
        $product->setStock($data['stock']);
        $product->setIsActive($data['isActive'] ?? true);

        // persist() prépare l'insertion ; flush() envoie effectivement le SQL.
        $em->persist($product);
        $em->flush();

        return $this->json(['id' => $product->getId(), 'message' => 'Créé'], Response::HTTP_CREATED);
    }

    /** BASIC 4/6 — PUT : les champs modifiables constituent le nouvel état. */
    #[Route('/basic/{id}', name: 'basic_replace', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[OA\Put(
        summary: 'Basic — remplacer un produit',
        tags: ['Products / 1 — Basic'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            type: 'object', required: ['name', 'price', 'stock', 'isActive'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Lampe LED'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Blanche'),
                new OA\Property(property: 'price', type: 'string', example: '19.90'),
                new OA\Property(property: 'stock', type: 'integer', example: 7),
                new OA\Property(property: 'isActive', type: 'boolean', example: true),
            ],
        )),
        responses: [new OA\Response(response: 200, description: 'Produit remplacé'), new OA\Response(response: 404, description: 'Produit introuvable')],
    )]
    public function basicReplace(int $id, Request $request, ProductRepository $repository, EntityManagerInterface $em): JsonResponse
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

        // Pas de persist() : l'objet chargé depuis le repository est déjà géré.
        $em->flush();
        return $this->json(['id' => $product->getId(), 'message' => 'Remplacé']);
    }

    /**
     * BASIC 5/6 — PATCH : ne modifier QUE les clés présentes.
     * array_key_exists('description', $data) distingue une clé absente de
     * {"description":null}, qui doit vraiment effacer cette description.
     */
    #[Route('/basic/{id}', name: 'basic_patch', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Basic — modifier quelques champs',
        tags: ['Products / 1 — Basic'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            type: 'object', properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Lampe plus puissante'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'price', type: 'string', example: '20.90'),
                new OA\Property(property: 'stock', type: 'integer', example: 4),
                new OA\Property(property: 'isActive', type: 'boolean', example: false),
            ],
        )),
        responses: [new OA\Response(response: 200, description: 'Champs fournis modifiés'), new OA\Response(response: 404, description: 'Produit introuvable')],
    )]
    public function basicPatch(int $id, Request $request, ProductRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['message' => 'Produit introuvable.'], 404);
        }
        $data = $request->toArray();

        if (array_key_exists('name', $data)) {
            $product->setName($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $product->setDescription($data['description']);
        }
        if (array_key_exists('price', $data)) {
            $product->setPrice($data['price']);
        }
        if (array_key_exists('stock', $data)) {
            $product->setStock($data['stock']);
        }
        if (array_key_exists('isActive', $data)) {
            $product->setIsActive($data['isActive']);
        }

        $em->flush();
        return $this->json(['id' => $product->getId(), 'message' => 'Modifié']);
    }

    /** BASIC 6/6 — DELETE : statut 204 sans aucun body. */
    #[Route('/basic/{id}', name: 'basic_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Basic — supprimer un produit', tags: ['Products / 1 — Basic'], responses: [new OA\Response(response: 204, description: 'Supprimé sans corps HTTP'), new OA\Response(response: 404, description: 'Produit introuvable')])]
    public function basicDelete(int $id, ProductRepository $repository, EntityManagerInterface $em): Response
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['message' => 'Produit introuvable.'], 404);
        }
        $em->remove($product);
        $em->flush();
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    // ======================================================================
    // NIVEAU 2 — TAG OA : même mécanique, descriptions HTTP approfondies.
    // ======================================================================

    /**
     * TAG OA 1/6 — La logique de lecture est presque identique à basicIndex.
     * La vraie nouveauté se trouve AU-DESSUS de la méthode : #[OA\Get].
     * OpenAPI décrit la requête/réponse ; Symfony Route exécute la requête.
     */
    #[Route('/tagOA', name: 'tagoa_index', methods: ['GET'])]
    #[OA\Get(
        summary: 'Lister les produits — contrat OpenAPI détaillé',
        description: 'Récupérer la collection entière des produits, classée par identifiant croissant. '
            .'Le contrôleur lit les entités avec Doctrine, sélectionne manuellement six champs '
            .'et les encode en JSON. Aucun filtre ni pagination dans ce laboratoire. '
            .'La description OpenAPI informe le client ; elle ne modifie pas le résultat.',
        tags: ['Products / 2 — TagOA'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Succès : toujours un tableau JSON, éventuellement vide lorsque la table ne contient aucun produit.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/LearningProduct'),
                    example: [[
                        'id' => 5,
                        'name' => 'Clavier mécanique',
                        'description' => 'AZERTY compact',
                        'price' => '49.90',
                        'stock' => 12,
                        'isActive' => true,
                    ]],
                ),
            ),
        ],
    )]
    public function tagOaIndex(ProductRepository $repository): JsonResponse
    {
        $result = [];
        foreach ($repository->findBy([], ['id' => 'ASC']) as $product) {
            $result[] = [
                'id' => $product->getId(), 'name' => $product->getName(),
                'description' => $product->getDescription(), 'price' => $product->getPrice(),
                'stock' => $product->getStock(), 'isActive' => $product->isActive(),
            ];
        }
        return $this->json($result);
    }

    /** TAG OA 2/6 — Un paramètre de chemin et deux issues (200/404). */
    #[Route('/tagOA/{id}', name: 'tagoa_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        summary: 'Lire un produit identifié par son id',
        description: 'Recevoir un produit par son identifiant numérique. La contrainte de route '
            .'requirements empêche de faire correspondre une URL contenant des lettres. '
            .'En revanche, seul le Repository vérifie si cet identifiant existe. '
            .'Un id valide dans l’URL, mais absent de la base, reçoit 404.',
        tags: ['Products / 2 — TagOA'],
        parameters: [
            new OA\Parameter(
                name: 'id', in: 'path', required: true,
                description: 'Identifiant positif de la ressource cherchée, à relever via GET collection.',
                schema: new OA\Schema(type: 'integer', minimum: 1), example: 5,
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Un objet Product : les six champs publics sont renvoyés.',
                content: new OA\JsonContent(ref: '#/components/schemas/LearningProduct'),
            ),
            new OA\Response(
                response: 404,
                description: 'L’URL a bien été reconnue, mais aucun produit ne possède cet id.',
                content: new OA\JsonContent(ref: '#/components/schemas/LearningProductNotFound'),
            ),
        ],
    )]
    public function tagOaShow(int $id, ProductRepository $repository): JsonResponse
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['message' => 'Produit introuvable.'], 404);
        }
        return $this->json([
            'id' => $product->getId(), 'name' => $product->getName(),
            'description' => $product->getDescription(), 'price' => $product->getPrice(),
            'stock' => $product->getStock(), 'isActive' => $product->isActive(),
        ]);
    }

    /**
     * TAG OA 3/6 — Un requestBody décrit le JSON d'entrée.
     * Le schéma réutilisable vient de #[OA\Schema(schema: LearningProductCreate)]
     * au-dessus de la classe ; Swagger l'affiche, le serveur ne le valide pas.
     */
    #[Route('/tagOA', name: 'tagoa_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Créer un produit — démonstration requestBody et 201',
        description: 'Envoyer un objet JSON représentant un nouveau produit. '
            .'Le serveur attribue lui-même l’identifiant et enregistre la nouvelle entité avec Doctrine. '
            .'name, price et stock sont attendus ; description est facultative (null par défaut), '
            .'isActive vaut true par défaut. Le prix est une chaîne, par exemple "49.90". '
            .'Attention : ce niveau documente le contrat, mais N’ACTIVE PAS les contrôles Validator. '
            .'Pour observer les rejets propres des données invalides, utiliser Verified ou Final.',
        tags: ['Products / 2 — TagOA'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Objet JSON à insérer. Ne pas envoyer id : il est généré par la base.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/LearningProductCreate',
                example: [
                    'name' => 'Lampe de bureau', 'description' => 'Recharge USB-C',
                    'price' => '18.90', 'stock' => 5, 'isActive' => true,
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Création effectuée. La réponse contient le nouvel identifiant, sans inclure tout le produit.',
                content: new OA\JsonContent(
                    type: 'object', required: ['id', 'message'],
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 42),
                        new OA\Property(property: 'message', type: 'string', example: 'Créé'),
                    ],
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'JSON illisible par Request::toArray() ; erreur générée par Symfony, non normalisée ici.',
            ),
        ],
    )]
    public function tagOaCreate(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = $request->toArray();
        $product = (new Product())
            ->setName($data['name'])
            ->setDescription($data['description'] ?? null)
            ->setPrice($data['price'])
            ->setStock($data['stock'])
            ->setIsActive($data['isActive'] ?? true);
        $em->persist($product);
        $em->flush();
        return $this->json(['id' => $product->getId(), 'message' => 'Créé'], 201);
    }

    /** TAG OA 4/6 — Documenter une requête avec id ET body JSON. */
    #[Route('/tagOA/{id}', name: 'tagoa_replace', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[OA\Put(
        summary: 'Remplacer les champs modifiables d’un produit',
        description: 'PUT reçoit le nouvel état complet des champs modifiables d’un produit existant. '
            .'Les champs name, price, stock et isActive sont attendus à chaque remplacement. '
            .'description peut être absente, auquel cas sa valeur devient null. '
            .'L’identifiant reste celui présent dans le chemin. '
            .'Doctrine suit déjà l’entité obtenue par le Repository : flush() suffit, sans persist(). '
            .'Les contraintes documentées ici ne sont pas validées par ce contrôleur de niveau 2.',
        tags: ['Products / 2 — TagOA'],
        parameters: [new OA\Parameter(
            name: 'id', in: 'path', required: true,
            description: 'Identifiant du produit à remplacer. Il doit déjà exister.',
            schema: new OA\Schema(type: 'integer', minimum: 1), example: 5,
        )],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Fournir l’état complet modifiable, pas seulement les propriétés changées.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/LearningProductReplace',
                example: ['name' => 'Lampe blanche', 'description' => null, 'price' => '19.90', 'stock' => 7, 'isActive' => true],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Remplacement appliqué à la ressource existante.',
                content: new OA\JsonContent(
                    type: 'object', required: ['id', 'message'],
                    properties: [new OA\Property(property: 'id', type: 'integer', example: 5), new OA\Property(property: 'message', type: 'string', example: 'Remplacé')],
                ),
            ),
            new OA\Response(response: 404, description: 'Aucun produit avec cet id', content: new OA\JsonContent(ref: '#/components/schemas/LearningProductNotFound')),
            new OA\Response(response: 400, description: 'Corps JSON malformé : erreur Symfony non normalisée ici'),
        ],
    )]
    public function tagOaReplace(int $id, Request $request, ProductRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['message' => 'Produit introuvable.'], 404);
        }
        $data = $request->toArray();
        $product
            ->setName($data['name'])
            ->setDescription($data['description'] ?? null)
            ->setPrice($data['price'])
            ->setStock($data['stock'])
            ->setIsActive($data['isActive']);
        $em->flush();
        return $this->json(['id' => $product->getId(), 'message' => 'Remplacé']);
    }

    /** TAG OA 5/6 — Décrire le contrat PATCH : propriétés facultatives. */
    #[Route('/tagOA/{id}', name: 'tagoa_patch', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Mettre à jour uniquement les champs présents dans le JSON',
        description: 'PATCH permet de ne modifier qu’une partie de la ressource. '
            .'Envoyer {"stock":4} ne touche pas au nom, au prix ni à la description. '
            .'Envoyer {"description":null} efface explicitement la description. '
            .'Le code distingue présence et absence grâce à array_key_exists(). '
            .'Ce contrôleur n’applique pas encore la validation des types ni la liste blanche stricte.',
        tags: ['Products / 2 — TagOA'],
        parameters: [new OA\Parameter(
            name: 'id', in: 'path', required: true,
            description: 'Identifiant de la ressource à modifier partiellement.',
            schema: new OA\Schema(type: 'integer', minimum: 1), example: 5,
        )],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Ne renseigner que les champs à modifier. description accepte null.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/LearningProductPatch',
                example: ['stock' => 4],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Mise à jour enregistrée. Seules les propriétés envoyées sont modifiées.',
                content: new OA\JsonContent(
                    type: 'object', required: ['id', 'message'],
                    properties: [new OA\Property(property: 'id', type: 'integer', example: 5), new OA\Property(property: 'message', type: 'string', example: 'Modifié')],
                ),
            ),
            new OA\Response(response: 404, description: 'Produit absent', content: new OA\JsonContent(ref: '#/components/schemas/LearningProductNotFound')),
            new OA\Response(response: 400, description: 'JSON malformé : erreur Symfony non normalisée ici'),
        ],
    )]
    public function tagOaPatch(int $id, Request $request, ProductRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['message' => 'Produit introuvable.'], 404);
        }
        $data = $request->toArray();
        if (array_key_exists('name', $data)) {
            $product->setName($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $product->setDescription($data['description']);
        }
        if (array_key_exists('price', $data)) {
            $product->setPrice($data['price']);
        }
        if (array_key_exists('stock', $data)) {
            $product->setStock($data['stock']);
        }
        if (array_key_exists('isActive', $data)) {
            $product->setIsActive($data['isActive']);
        }
        $em->flush();
        return $this->json(['id' => $product->getId(), 'message' => 'Modifié']);
    }

    /** TAG OA 6/6 — Réponse 204 : documenter l'ABSENCE de contenu. */
    #[Route('/tagOA/{id}', name: 'tagoa_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Supprimer définitivement un produit',
        description: 'Retirer la ligne correspondant à cet id de la table Product. '
            .'Pour un produit existant, le serveur répond 204 sans corps JSON. '
            .'Ne pas déclarer de content dans OA\\Response(204) : une réponse 204 est vide. '
            .'Une seconde suppression sur le même id renvoie 404, car la ligne n’existe plus.',
        tags: ['Products / 2 — TagOA'],
        parameters: [new OA\Parameter(
            name: 'id', in: 'path', required: true,
            description: 'Identifiant existant à supprimer.',
            schema: new OA\Schema(type: 'integer', minimum: 1), example: 5,
        )],
        responses: [
            new OA\Response(response: 204, description: 'Suppression réussie, aucun contenu dans la réponse.'),
            new OA\Response(response: 404, description: 'Le produit a déjà été supprimé ou n’a jamais existé.', content: new OA\JsonContent(ref: '#/components/schemas/LearningProductNotFound')),
        ],
    )]
    public function tagOaDelete(int $id, ProductRepository $repository, EntityManagerInterface $em): Response
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['message' => 'Produit introuvable.'], 404);
        }
        $em->remove($product);
        $em->flush();
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    // ======================================================================
    // NIVEAU 3 — VERIFIED : DTO, Serializer, Validator, MapRequestPayload.
    // ======================================================================

    /**
     * VERIFIED 1/6 — Serializer en SORTIE, avec les Groups de Product.php.
     * Avant : fabriquer chaque tableau à la main (basicIndex/tagOaIndex).
     * Ici : json() utilise le Serializer de Symfony et n'expose que les champs
     * annotés #[Groups(['product:read'])] sur l'entité.
     */
    #[Route('/verified', name: 'verified_index', methods: ['GET'])]
    #[OA\Get(summary: 'Verified — GET avec Serializer et Groups', tags: ['Products / 3 — Verified'], responses: [new OA\Response(response: 200, description: 'Liste de produits sérialisés avec product:read')])]
    public function verifiedIndex(ProductRepository $repository): JsonResponse
    {
        $products = $repository->findBy([], ['id' => 'ASC']);
        // 4e argument de json() : contexte transmis au Serializer.
        return $this->json($products, 200, [], ['groups' => ['product:read']]);
    }

    /**
     * VERIFIED 2/6 — DTO en SORTIE : ne plus exposer directement Product.
     * ProductOutput::fromEntity() choisit le contrat public, indépendant des
     * attributs Doctrine et des éventuels nouveaux champs internes.
     */
    #[Route('/verified/{id}', name: 'verified_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        summary: 'Verified — GET avec DTO de sortie', tags: ['Products / 3 — Verified'],
        responses: [
            new OA\Response(response: 200, description: 'Produit transformé en ProductOutput', content: new OA\JsonContent(ref: new Model(type: ProductOutput::class))),
            new OA\Response(response: 404, description: 'Produit introuvable'),
        ],
    )]
    public function verifiedShow(int $id, ProductRepository $repository): JsonResponse
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['message' => 'Produit introuvable.'], 404);
        }
        return $this->json(ProductOutput::fromEntity($product));
    }

    /**
     * VERIFIED 3/6 — JSON -> DTO d'entrée -> Validator -> Writer -> DTO sortie.
     * Démonstration EXPLICITE des composants avant MapRequestPayload (PUT).
     * Le DTO décrit les champs ; le Serializer les transporte ; le Validator
     * applique les #[Assert] ; ProductWriter persiste après validation.
     */
    #[Route('/verified', name: 'verified_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Verified — POST avec Serializer + Validator explicites',
        tags: ['Products / 3 — Verified'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CreateProductInput::class))),
        responses: [
            new OA\Response(response: 201, description: 'Produit créé après validation', content: new OA\JsonContent(ref: new Model(type: ProductOutput::class))),
            new OA\Response(response: 400, description: 'JSON invalide ou types impossibles à convertir'),
            new OA\Response(response: 415, description: 'Content-Type différent de application/json'),
            new OA\Response(response: 422, description: 'Contraintes Assert violées ou champs inconnus'),
        ],
    )]
    public function verifiedCreate(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ProductWriter $writer,
    ): JsonResponse {
        $data = $this->verifiedJsonObject($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $unknown = array_diff(array_keys($data), ['name', 'description', 'price', 'stock', 'isActive']);
        if ($unknown !== []) {
            return $this->json(['code' => 'UNKNOWN_FIELDS', 'fields' => array_values($unknown)], 422);
        }

        try {
            // Symfony remplit les propriétés publiques du DTO avec le body JSON.
            $input = $serializer->deserialize($request->getContent(), CreateProductInput::class, 'json');
        } catch (SerializerExceptionInterface | \TypeError | \ValueError) {
            return $this->json(['code' => 'INVALID_TYPES'], 400);
        }
        if (!$input instanceof CreateProductInput) {
            return $this->json(['code' => 'INVALID_TYPES'], 400);
        }

        // Les Assert\... ne sont exécutés QU'À PARTIR de cette ligne.
        $violations = $validator->validate($input);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $violation) {
                $details[] = ['field' => $violation->getPropertyPath(), 'message' => $violation->getMessage()];
            }
            return $this->json(['code' => 'VALIDATION_FAILED', 'details' => $details], 422);
        }

        // Seul un DTO dont les contraintes ont été vérifiées arrive au Writer.
        return $this->json(ProductOutput::fromEntity($writer->create($input)), 201);
    }

    /**
     * VERIFIED 4/6 — MapRequestPayload : version condensée de l'entrée.
     * Symfony s'occupe du décodage, de la création du DTO et de sa validation.
     * Les erreurs automatiques sont au FORMAT SYMFONY (pas ApiErrorOutput).
     * La route utilise format:json pour que les erreurs soient en JSON.
     */
    #[Route('/verified/{id}', name: 'verified_replace', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[OA\Put(
        summary: 'Verified — PUT avec MapRequestPayload', tags: ['Products / 3 — Verified'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: ReplaceProductInput::class))),
        responses: [
            new OA\Response(response: 200, description: 'Produit remplacé', content: new OA\JsonContent(ref: new Model(type: ProductOutput::class))),
            new OA\Response(response: 400, description: 'Payload incorrect'),
            new OA\Response(response: 404, description: 'Produit introuvable'),
            new OA\Response(response: 415, description: 'Format de requête non accepté'),
            new OA\Response(response: 422, description: 'Violation Assert ; erreur produite automatiquement par Symfony'),
        ],
    )]
    public function verifiedReplace(
        int $id,
        #[MapRequestPayload(acceptFormat: 'json')] ReplaceProductInput $input,
        ProductRepository $repository,
        ProductWriter $writer,
    ): JsonResponse {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['message' => 'Produit introuvable.'], 404);
        }

        return $this->json(ProductOutput::fromEntity($writer->replace($product, $input)));
    }

    /**
     * VERIFIED 5/6 — PATCH : valeurs et présence des champs sont distinctes.
     * DTO nullable pour les champs absents ; array_keys() pour savoir lesquels
     * sont réellement envoyés. En plus des Assert, null explicite est refusé
     * pour name/price/stock/isActive, mais autorisé pour description.
     */
    #[Route('/verified/{id}', name: 'verified_patch', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Verified — PATCH avec DTO et validation partielle',
        tags: ['Products / 3 — Verified'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: PatchProductInput::class))),
        responses: [
            new OA\Response(response: 200, description: 'Produit modifié', content: new OA\JsonContent(ref: new Model(type: ProductOutput::class))),
            new OA\Response(response: 400, description: 'JSON ou types invalides'),
            new OA\Response(response: 404, description: 'Produit introuvable'),
            new OA\Response(response: 415, description: 'Format non pris en charge'),
            new OA\Response(response: 422, description: 'Champ inconnu, null interdit ou violation Assert'),
        ],
    )]
    public function verifiedPatch(
        int $id,
        Request $request,
        ProductRepository $repository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ProductWriter $writer,
    ): JsonResponse {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['message' => 'Produit introuvable.'], 404);
        }
        $data = $this->verifiedJsonObject($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        if ($data === []) {
            return $this->json(['code' => 'EMPTY_PATCH'], 422);
        }
        $unknown = array_diff(array_keys($data), ['name', 'description', 'price', 'stock', 'isActive']);
        if ($unknown !== []) {
            return $this->json(['code' => 'UNKNOWN_FIELDS', 'fields' => array_values($unknown)], 422);
        }
        foreach (['name', 'price', 'stock', 'isActive'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                return $this->json(['code' => 'NULL_NOT_ALLOWED', 'field' => $field], 422);
            }
        }

        try {
            $input = $serializer->deserialize($request->getContent(), PatchProductInput::class, 'json');
        } catch (SerializerExceptionInterface | \TypeError | \ValueError) {
            return $this->json(['code' => 'INVALID_TYPES'], 400);
        }
        if (!$input instanceof PatchProductInput) {
            return $this->json(['code' => 'INVALID_TYPES'], 400);
        }
        $violations = $validator->validate($input);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $violation) {
                $details[] = ['field' => $violation->getPropertyPath(), 'message' => $violation->getMessage()];
            }
            return $this->json(['code' => 'VALIDATION_FAILED', 'details' => $details], 422);
        }

        // Le Writer doit recevoir cette liste pour distinguer absent de null.
        return $this->json(ProductOutput::fromEntity($writer->patch($product, $input, array_keys($data))));
    }

    /** VERIFIED 6/6 — Déléguer l'écriture au service, garder l'HTTP ici. */
    #[Route('/verified/{id}', name: 'verified_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Verified — DELETE avec ProductWriter', tags: ['Products / 3 — Verified'], responses: [new OA\Response(response: 204, description: 'Supprimé, réponse vide'), new OA\Response(response: 404, description: 'Produit absent')])]
    public function verifiedDelete(int $id, ProductRepository $repository, ProductWriter $writer): Response
    {
        $product = $repository->find($id);
        if ($product === null) {
            return $this->json(['message' => 'Produit introuvable.'], 404);
        }
        $writer->delete($product);
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Précontrôle HTTP commun à POST et PATCH Verified.
     * Retourner une réponse d'erreur OU les données du JSON objet.
     *
     * @return array<string, mixed>|JsonResponse
     */
    private function verifiedJsonObject(Request $request): array|JsonResponse
    {
        if ($request->getContentTypeFormat() !== 'json') {
            return $this->json(['code' => 'UNSUPPORTED_MEDIA_TYPE'], 415);
        }

        try {
            $decoded = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['code' => 'INVALID_JSON'], 400);
        }

        // Une liste JSON [] n'est pas un objet JSON {}.
        if (!$decoded instanceof \stdClass) {
            return $this->json(['code' => 'INVALID_JSON_OBJECT'], 400);
        }

        /** @var array<string, mixed> $data */
        $data = (array) $decoded;
        return $data;
    }
}
