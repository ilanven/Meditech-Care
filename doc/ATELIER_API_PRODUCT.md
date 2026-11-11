# Atelier API REST Symfony — construire `ProductController` pas à pas

> **Projet :** Meditech Care / BTS SIO SLAM — exercice autonome, sans lien avec le matériel ou les interventions du projet.  
> **Objectif :** comprendre *pourquoi* apparaissent, dans cet ordre, Repository, HTTP, JSON, Serializer, DTO, Validator, MapRequestPayload, service métier et OpenAPI.  
> **Version de référence :** Symfony 8, PHP 8.3+, Doctrine ORM, Symfony Serializer/Validator et NelmioApiDocBundle.  
> **Méthode de travail :** créer les fichiers dans le projet manuellement, suivre les étapes dans l'ordre, exécuter les requêtes et comparer les réponses. Ne pas confondre les méthodes d'apprentissage avec un modèle prêt pour la production.

---

## Sommaire

1. [Les deux contrôleurs, deux objectifs](#1-les-deux-contrôleurs-deux-objectifs)
2. [L'entité Product](#2-lentité-product)
3. [Installer et préparer](#3-installer-et-préparer)
4. [Arborescence fournie](#4-arborescence-fournie)
5. [Le trajet d'une requête](#5-le-trajet-dune-requête)
6. [Parcours progressif — 13 étapes](#6-parcours-progressif--13-étapes)
7. [Version finale : analyser chaque endpoint](#7-version-finale--analyser-chaque-endpoint)
8. [Référentiel des codes HTTP](#8-référentiel-des-codes-http)
9. [Recettes de test HTTP et Swagger](#9-recettes-de-test-http-et-swagger)
10. [Pièges et choix d'architecture](#10-pièges-et-choix-darchitecture)
11. [Correspondance avec le cours](#11-correspondance-avec-le-cours)
12. [Vérifications et limites](#12-vérifications-et-limites)

---

## 1. Les deux contrôleurs, deux objectifs

### `ProductController.php` — le laboratoire

Base : `/api/learning/products`. On garde des méthodes simples **exprès** pour comparer les techniques. Les premières étapes n'ont ni DTO, ni contraintes, ni documentation OpenAPI. Certaines requêtes invalides peuvent échouer avec une erreur 500 : c'est précisément un des problèmes que l'on apprend ensuite à traiter.

À la fin, les nouvelles versions d'une opération sont conservées avec des routes `/demo/...`. Il n'est donc pas nécessaire de remplacer le premier POST à chaque fois.

### `ProductFinalController.php` — la référence de l'atelier

Base : `/api/final/products`. Il expose six opérations REST (`GET` collection, `GET` unitaire, `POST`, `PUT`, `PATCH`, `DELETE`) :

- DTO d'entrée différents selon l'opération ;
- DTO de sortie stable, sans renvoyer directement l'entité ;
- Serializer pour transformer le JSON en DTO ;
- Validator et contraintes `Assert` ;
- service `ProductWriter` pour la persistance ;
- erreurs JSON homogènes pour les cas traités ;
- codes HTTP et documentation OpenAPI cohérents ;
- garde-fous contre les champs inconnus et mise à jour partielle explicite.

**Ce n'est pas une API de production :** l'authentification et les autorisations ne sont volontairement pas activées pour conserver un atelier testable immédiatement. Des routes d'écriture publiques seraient dangereuses sur un serveur exposé. Voir §10.

### Pourquoi séparer les routes ?

Les deux contrôleurs accèdent à la **même table `product`**, mais ne peuvent pas publier tous les deux `GET /api/products/{id}`. On utilise donc deux préfixes. Les chemins `/demo/...` désignent des variations **pédagogiques**, pas une stratégie de versionnement d'API publique.

## 2. L'entité Product

Créer `Product` avec `php bin/console make:entity Product`, ou utiliser `src/Entity/Product.php` fourni comme modèle exact.

| Champ | Type Doctrine | Type PHP dans l'entité | Nullable | Remarque |
|---|---|---|---|---|
| `id` | integer, auto | `?int` | non en BDD | clé primaire, lecture seule dans l'API |
| `name` | string(150) | `?string` | non | nom, validé via DTO |
| `description` | text | `?string` | oui | peut être remis à `null` en PATCH |
| `price` | decimal(10,2) | `?string` | non | chaîne dans le JSON : `"49.90"` |
| `stock` | integer | `?int` | non | entier positif ou nul |
| `isActive` | boolean | `bool` | non | actif par défaut |

Les getters/setters sont déjà définis dans la version fournie. Pour une entité générée par Maker, vérifier **exactement** les noms `getPrice()`, `setPrice(string)`, `isActive()` et `setIsActive(bool)` : les deux contrôleurs et les fixtures les utilisent.

### Pourquoi `price` est une `string` ?

Un `DECIMAL` Doctrine vise une valeur décimale exacte ; DBAL le restitue généralement comme une chaîne. Le `float` PHP est approximatif : `0.1 + 0.2` ne représente pas toujours exactement `0.3`. Un DTO qui exige `"49.90"` rend visible ce contrat. On n'effectue aucun calcul financier ici.

### Pourquoi des `#[Groups(['product:read'])]` sur l'entité ?

Uniquement pour l'**étape 6**. Ils montrent comment restreindre la sérialisation directe d'une entité. L'API finale préfère `ProductOutput` : le contrat externe ne dépend alors plus de ces groupes.

## 3. Installer et préparer

Vérifier les composants nécessaires dans ton projet, sans installer deux fois ceux déjà présents :

```bash
composer require symfony/serializer symfony/validator
composer require nelmio/api-doc-bundle
composer require --dev orm-fixtures
```

L'exercice suppose Doctrine ORM et Maker déjà présents. Si `make:entity` n'est pas disponible : `composer require --dev symfony/maker-bundle`.

Copier les fichiers du dossier `src/` fourni dans les mêmes chemins sous le `src/` du projet. **Ne pas écraser une entité Product ou un repository Product existant sans comparaison préalable.**

Après création de l'entité :

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
php bin/console debug:router | grep -E 'api_(learning|final)_products'
```

Vérifier et relire la migration avant son exécution si le projet contient déjà des données.

### Fixtures — attention à la base de données

`php bin/console doctrine:fixtures:load` **purge par défaut les données de la base**. Ne pas l'utiliser sans précaution dans la base principale de Meditech Care.

Pour ajouter uniquement les produits de démonstration **sans purger les autres tables** :

```bash
php bin/console doctrine:fixtures:load --group=ProductFixtures --append
```

Cette commande insère 10 produits. **Attention : la relancer avec `--append` ajoute à nouveau 10 produits** ; elle ne nettoie pas les précédents. Idéalement, utiliser une base de développement dédiée ou une base de test jetable. Vérifier avec `php bin/console doctrine:fixtures:load --group=ProductFixtures --append --dry-run` si cette option existe dans la version installée.

Les identifiants auto-incrémentés ne sont **pas garantis égaux à 1…10**, surtout si la base contient d'autres produits. Toujours relever un ID via GET collection avant de tester les routes `/{id}`.

### Swagger UI / Nelmio

Si Nelmio est déjà configuré, conserver la configuration du projet. Sinon, exemples minimaux **à adapter, pas à écraser aveuglément** :

```yaml
# config/packages/nelmio_api_doc.yaml
nelmio_api_doc:
    documentation:
        info:
            title: Meditech Care — atelier Products
            description: Exercices de création d'API Symfony
            version: 1.0.0
    areas:
        path_patterns:
            - ^/api(?!/doc$)
```

```yaml
# config/routes/nelmio_api_doc.yaml
app.swagger_ui:
    path: /api/doc
    methods: GET
    defaults:
        _controller: nelmio_api_doc.controller.swagger_ui
```

Selon l'installation, les routes de Nelmio peuvent déjà être générées. Vérifier `debug:router` et ouvrir `/api/doc` (ou l'URL de documentation déjà configurée). Pour le fonctionnement de Swagger UI, le `requestBody` doit être **décrit dans OpenAPI** : les premières méthodes du tutoriel n'ont volontairement pas cette description, ce qui limite la saisie interactive des POST dans Swagger UI. On les testera avec un client HTTP ou les commandes `curl` ci-dessous ; les méthodes OpenAPI de l'étape 13 et du contrôleur final seront pleinement testables dans Swagger.

> **Note technique** : ce kit est conçu pour Symfony 7 et les attributs PHP 8 `OpenApi\Attributes as OA`. Le cours montre aussi la syntaxe historique `@OA\...` dans des commentaires PHPDoc ; ne pas mélanger les deux formes pour le même endpoint.

## 4. Arborescence fournie

```text
src/
├── Controller/Api/
│   ├── ProductController.php          # 13 étapes commentées
│   └── ProductFinalController.php     # CRUD final documenté
├── Dto/Product/
│   ├── CreateProductInput.php
│   ├── ReplaceProductInput.php
│   ├── PatchProductInput.php
│   ├── ProductOutput.php
│   └── ApiErrorOutput.php
├── Service/
│   └── ProductWriter.php
├── Entity/
│   └── Product.php                   # référence exacte à créer/vérifier
├── Repository/
│   └── ProductRepository.php        # référence compatible avec make:entity
└── DataFixtures/
    └── ProductFixtures.php
```

### Rôle des classes

| Classe | Sa responsabilité | Ce qu'elle ne doit pas faire |
|---|---|---|
| `Product` | représenter une ligne de la BDD | décider du contenu de la réponse publique |
| `ProductRepository` | lire les produits avec Doctrine | interpréter le body HTTP |
| `CreateProductInput` | décrire les champs autorisés pour POST | avoir un `id` modifiable |
| `ReplaceProductInput` | décrire le PUT complet | deviner les champs absents |
| `PatchProductInput` | décrire les valeurs possibles de PATCH | décider seul si un champ était présent |
| `ProductOutput` | figer la forme JSON sortante | faire des requêtes SQL |
| `ApiErrorOutput` | unifier le contrat des erreurs traitées | exposer des traces ou erreurs SQL |
| `ProductWriter` | créer, modifier, supprimer via Doctrine | retourner des `JsonResponse` ou codes HTTP |
| Les contrôleurs | lire l'HTTP, appeler les composants, renvoyer l'HTTP | contenir de gros traitements métier |
| `ProductFixtures` | injecter un jeu d'essai lisible | servir de données de production |

## 5. Le trajet d'une requête

### Lecture

```text
GET /api/final/products/42
  → Route Symfony (correspondance URL + méthode)
  → ProductFinalController::show(42)
  → ProductRepository::find(42)
  → ProductOutput::fromEntity(Product)
  → AbstractController::json(ProductOutput)
  → HTTP 200 application/json
```

### Création

```text
POST /api/final/products + body JSON
  → Route Symfony
  → Contrôleur : contrôle Content-Type et format JSON
  → Serializer : JSON -> CreateProductInput
  → Validator : Assert sur le DTO
  → ProductWriter::create() : DTO -> entité -> persist() -> flush()
  → ProductOutput : entité -> sortie publique
  → HTTP 201 + header Location + body JSON
```

**Nuance importante :** la désérialisation convertit des données, elle ne les **valide** pas au sens des règles `Assert`. Les deux étapes sont distinctes, même lorsque `MapRequestPayload` les enchaîne pour nous.

## 6. Parcours progressif — 13 étapes

### Étape 1 — GET collection : lire et rendre du JSON

`GET /api/learning/products`

Découvrir `#[Route]`, la méthode GET, l'injection de `ProductRepository`, `findAll()`, un tableau PHP et `$this->json()`. Vérifier que la réponse a le statut **200**, le `Content-Type` JSON et contient une liste. Le tableau de départ expose intentionnellement seulement quatre propriétés (`id`, `name`, `price`, `stock`).

**Pourquoi un tableau manuel ?** C'est explicite. Avant de connaître Serializer ou DTO, on voit précisément les données qui quittent le serveur. **Limite :** code répétitif et risque d'oublier un champ lors de changements.

### Étape 2 — GET par ID : paramètre de route, 404

`GET /api/learning/products/{id}`

Observer `/{id}` dans la route et l'argument PHP `int $id`. Symfony fournit cet ID au contrôleur. Le repository utilise `find($id)`. Si le produit manque, on renvoie **404** plutôt que d'essayer d'accéder aux propriétés d'un objet `null`.

**Question à se poser :** que doit recevoir un client qui demande `/999999` ? Une erreur métier de ressource absente, et non une page HTML d'exception PHP.

### Étape 3 — POST V1 : créer de manière directe

`POST /api/learning/products`

Découvrir `Request`, `$request->toArray()`, les setters, `persist()`, `flush()` et **201 Created**. Réponse minimale : `{"id": ..., "message": "Créé"}`. `persist()` indique à Doctrine de suivre une nouvelle entité ; `flush()` déclenche réellement l'écriture.

**Piège volontaire :** les champs `name`, `price` et `stock` sont lus sans validation. Le payload `{}` peut provoquer une erreur. Ce contrôleur illustre pourquoi on ne peut pas considérer une requête cliente comme sûre.

### Étape 4 — PUT V1 : remplacer

`PUT /api/learning/products/{id}`

La ressource existe déjà. Avec Doctrine, modifier une entité récupérée par le repository puis appeler `flush()` suffit : pas besoin de `persist()` une seconde fois. PUT remplace l'ensemble des champs modifiables : envoyer tous `name`, `price`, `stock`, `isActive` et, pour préserver sa valeur, `description`.

**Attention :** ce PUT V1 n'applique pas encore le contrat complet ni la validation. La version finale le fera.

### Étape 5 — DELETE et 204

`DELETE /api/learning/products/{id}`

Découvrir `remove()` puis `flush()`. Réponse **204 No Content** : aucun JSON n'est attendu. Le client teste le code de retour et peut ensuite refaire un GET pour constater un 404.

### Étape 6 — GET V2 : Serializer + groupes

`GET /api/learning/products/demo/serializer`

Le contrôleur renvoie des entités mais avec un contexte de sérialisation :

```php
return $this->json($products, 200, [], ['groups' => ['product:read']]);
```

Les `#[Groups(['product:read'])]` présents sur les propriétés de `Product` contrôlent les champs convertis en JSON. **Gain :** on évite d'écrire les tableaux à la main. **Limite :** le contrat de l'API reste lié à l'entité Doctrine et à ses groupes.

### Étape 7 — GET V3 : DTO de sortie

`GET /api/learning/products/demo/output-dto`

On transforme chaque `Product` en `ProductOutput`. Le nom **DTO** signifie *Data Transfer Object*, un objet de transport de données. Il décrit la **sortie**, pas les colonnes SQL. L'appel `ProductOutput::fromEntity($product)` réalise un mapping explicite.

**Comparer :** le JSON ressemble à l'étape 6, mais la **responsabilité** a changé. Ajouter ultérieurement un champ privé à `Product` ne l'ajoute pas automatiquement à l'API.

### Étape 8 — POST V2 : DTO d'entrée + Serializer

`POST /api/learning/products/demo/input-dto`

Le client fournit du JSON ; le `SerializerInterface` crée un objet `CreateProductInput`. Les champs autorisés sont définis par la classe, sans inclure `id`.

```php
$input = $serializer->deserialize($request->getContent(), CreateProductInput::class, 'json');
```

**Attention :** désérialiser ne déclenche pas automatiquement les contraintes `Assert`. Une requête avec des valeurs absentes peut toujours faire échouer le traitement. Ne pas utiliser cette variante dans une API publique.

### Étape 9 — POST V3 : Validator + `Assert`

`POST /api/learning/products/demo/validated`

Après désérialisation, l'appel `$validator->validate($input)` retourne une liste de violations. Exemple : nom vide, prix `"-3"`, stock négatif. Le contrôleur convertit ces violations en HTTP **422 Unprocessable Content** et un tableau de champs/messages.

Les contraintes sont déclarées **dans le DTO** : `#[Assert\NotBlank]`, `#[Assert\Length]`, `#[Assert\Regex]`, `#[Assert\PositiveOrZero]`.

**Différence fondamentale :** le DTO définit *quoi* le client peut envoyer ; le Validator vérifie *si* ces valeurs respectent les contraintes. Les règles métier complexes (stock réservable, disponibilité d'un produit…) appartiendraient à un autre niveau.

### Étape 10 — POST V4 : `MapRequestPayload`

`POST /api/learning/products/demo/map-payload`

L'attribut Symfony `#[MapRequestPayload(acceptFormat: 'json')]` évite le décodage et la validation manuels. Symfony prépare l'objet et renvoie automatiquement une erreur lorsque les contraintes échouent. Le code du contrôleur devient court.

**Important :** les réponses d'erreur automatiques utilisent le mécanisme/format de Symfony. Pour maîtriser intégralement le contrat des erreurs, la version finale utilise une lecture explicite du DTO et une réponse `ApiErrorOutput`.

### Étape 11 — PATCH : distinguer absence et `null`

`PATCH /api/learning/products/demo/{id}/patch`

Comparer ces deux requêtes :

```json
{"stock": 4}
```

```json
{"description": null}
```

La première ne modifie que le stock. La seconde **efface la description**. Envoyer `"name": null` est refusé, car le nom est non-nullable. Nous conservons les **clés réellement présentes** du JSON ; le DTO, qui contient des valeurs `null` par défaut pour les champs absents, ne suffit pas seul à connaître cette distinction.

### Étape 12 — GET avec filtre : query parameter

`GET /api/learning/products/demo/filter?active=1`

Introduire `Request::query`, la validation explicite de `active` (`0` ou `1`) et `Repository::findBy()` avec critères. Le filtre est appliqué par Doctrine **au niveau de la requête SQL**, pas après avoir chargé toutes les entités pour les parcourir côté PHP.

Variantes : sans `active` → tous ; `active=0` → inactifs ; `active=banana` → **400**.

### Étape 13 — POST V5 : attribuer un contrat OpenAPI

`POST /api/learning/products/demo/documented`

On réutilise la logique concise de `MapRequestPayload`, mais on ajoute : `#[OA\Post]`, `new OA\RequestBody`, `new OA\Response`, `tags`, `summary`, et les schémas déduits des DTO via `new Model(type: ...::class)`.

**Comparer les rôles :** Symfony Route exécute ; les attributs OpenAPI **décrivent** ; Nelmio génère le document ; Swagger UI l'affiche et permet d'exécuter une vraie requête. La documentation ne crée pas la sécurité et ne remplace pas les validations ni les tests.

---

## 7. Version finale : analyser chaque endpoint

| HTTP | Route | Entrée | Sortie attendue | Succès |
|---|---|---|---|---|
| GET | `/api/final/products` | aucune | `ProductOutput[]` | 200 |
| GET | `/api/final/products/{id}` | id entier | `ProductOutput` | 200 |
| POST | `/api/final/products` | `CreateProductInput` | `ProductOutput` + `Location` | 201 |
| PUT | `/api/final/products/{id}` | `ReplaceProductInput` | `ProductOutput` | 200 |
| PATCH | `/api/final/products/{id}` | `PatchProductInput` et champs présents | `ProductOutput` | 200 |
| DELETE | `/api/final/products/{id}` | id entier | aucun body | 204 |

### GET : la sortie est maîtrisée

Le repository renvoie `Product`, puis `ProductOutput` choisit ce qui est public. Un attribut ajouté demain dans l'entité n'apparaîtra pas dans les réponses sans modification explicite du DTO de sortie.

### POST : l'entrée est maîtrisée

Le contrôleur exige un body JSON objet, refuse des champs inconnus et vérifie les champs requis. Le Serializer construit le DTO ; le Validator examine ses contraintes ; le service crée le produit ; le contrôleur retourne `201` avec un en-tête `Location` vers le GET unitaire.

**Mass assignment :** on n'utilise jamais `foreach ($data as $field => $value) $product->{'set'.ucfirst($field)}($value)`. Cela permettrait au client d'essayer de modifier arbitrairement des propriétés. Ici, la liste des champs est explicite.

### PUT : remplacement complet

Le client doit envoyer les quatre propriétés **non-nullables** : `name`, `price`, `stock`, `isActive`. `description` peut être omise, auquel cas elle devient `null`. Le service applique les valeurs validées à l'entité existante et fait `flush()`.

### PATCH : modification partielle

Une liste de clés présentes accompagne le `PatchProductInput`. La différence entre champ absent et champ à `null` ne peut pas être ignorée : `description` accepte `null`, mais les quatre autres champs ne l'acceptent pas. Le service ne touche qu'aux propriétés présentes.

### DELETE : réponse vide

Après `remove()` et `flush()`, HTTP 204 sans JSON. Sur un ID inexistant : 404 avec `ApiErrorOutput`.

### Les erreurs de la version finale

```json
{
  "code": "VALIDATION_FAILED",
  "message": "Les données ne respectent pas les contraintes.",
  "details": [
    {"field": "stock", "message": "This value should be positive or zero."}
  ]
}
```

Le texte exact d'une contrainte dépend de la langue configurée pour le Validator. La structure reste `code`, `message`, `details`. Les erreurs que nous construisons volontairement utilisent cette forme ; les exceptions **non prévues** et les erreurs générées par d'autres couches Symfony peuvent nécessiter un `ExceptionSubscriber` global si l'on veut imposer ce format à toute l'application. Cet atelier n'installe pas un subscriber qui pourrait modifier les réponses des autres modules Meditech Care.

### Pourquoi un service `ProductWriter` ?

La couche HTTP n'a pas à savoir **comment** la persistance est effectuée. Ici, le service reste petit et ne contient pas de vraie règle métier. Dans Meditech Care, un service pourrait ajouter des conditions métier complexes avant d'enregistrer une entité. Ne pas multiplier les classes pour des raisons purement décoratives.

---

## 8. Référentiel des codes HTTP

| Code | Situation dans l'atelier | Exemple |
|---|---|---|
| 200 | lecture ou modification réussie | GET / PUT / PATCH |
| 201 | ressource créée | POST |
| 204 | suppression réussie, aucun body | DELETE |
| 400 | JSON malformé, body non objet, mauvais types ou filtre incorrect | `{"stock":"pas un entier"}` |
| 404 | ressource inexistante | GET avec id inconnu |
| 415 | `Content-Type` non pris en charge | `text/plain` sur la version finale |
| 422 | données syntaxiquement reçues mais contraintes/contrat violés | `stock = -1`, nom vide, champ inconnu |

Les statuts 401 (non authentifié), 403 (non autorisé) et 409 (conflit métier) apparaissent dans le **cours général**, mais ne sont **pas inventés** dans cette API fictive dépourvue d'authentification et de contrainte métier de conflit. Le code 500 reste une erreur du serveur, jamais une manière normale de répondre à un champ invalide.

---

## 9. Recettes de test HTTP et Swagger

### 9.1 Préparer

Lancer le serveur Symfony comme d'habitude. Dans les exemples ci-dessous, l'API répond sur `http://127.0.0.1:8000` ; modifier cette adresse si nécessaire.

Tester d'abord la collection pour obtenir un ID existant :

```bash
curl -i http://127.0.0.1:8000/api/learning/products
curl -i http://127.0.0.1:8000/api/final/products
```

### 9.2 Création simple (étape 3)

```bash
curl -i -X POST http://127.0.0.1:8000/api/learning/products \
  -H 'Content-Type: application/json' \
  -d '{"name":"Lampe USB","description":"Pour bureau","price":"18.90","stock":5,"isActive":true}'
```

Attendu : **201**, avec l'ID nouvellement créé. Ne pas commencer par un JSON invalide sur cette étape : l'absence de validation est intentionnelle.

### 9.3 PUT simple (étape 4)

Remplacer `ID` par un ID existant :

```bash
curl -i -X PUT http://127.0.0.1:8000/api/learning/products/ID \
  -H 'Content-Type: application/json' \
  -d '{"name":"Lampe USB blanche","description":null,"price":"19.90","stock":7,"isActive":true}'
```

### 9.4 POST validé (étape 9)

```bash
curl -i -X POST http://127.0.0.1:8000/api/learning/products/demo/validated \
  -H 'Content-Type: application/json' \
  -d '{"name":"","price":"-4","stock":-2}'
```

Attendu : **422**, avec plusieurs violations, aucune ligne ajoutée.

### 9.5 POST MapRequestPayload (étape 10)

```bash
curl -i -X POST http://127.0.0.1:8000/api/learning/products/demo/map-payload \
  -H 'Content-Type: application/json' \
  -d '{"name":"Station USB","price":"36.90","stock":6}'
```

Attendu : **201**. Observer ensuite le traitement automatique d'un nom vide, puis comparer le format de l'erreur avec celui de l'étape 9.

### 9.6 PATCH : null / absent (étape 11)

```bash
curl -i -X PATCH http://127.0.0.1:8000/api/learning/products/demo/ID/patch \
  -H 'Content-Type: application/json' \
  -d '{"description":null}'
```

Le nom/prix/stock doivent rester inchangés, mais la description devient `null`.

### 9.7 Endpoint complètement documenté

Ouvrir `/api/doc` → groupe **Products - Apprentissage** → `POST /api/learning/products/demo/documented` → **Try it out** → renseigner le JSON → **Execute**. Vérifier `201` et le schéma de sortie. L'appel crée réellement un produit : Swagger n'est **pas** un simulateur.

### 9.8 Tester les six endpoints du contrôleur final

Dans Swagger, groupe **Products - Final**. Scénarios conseillés :

| Test | Requête | Résultat attendu |
|---|---|---|
| Lire tous | GET collection | 200, tableau |
| Lire un ID existant | GET `/{id}` | 200, objet |
| Lire un ID inconnu | GET `/999999999` | 404, `PRODUCT_NOT_FOUND` |
| Créer valide | POST avec `name`, `price`, `stock` | 201, `Location`, objet |
| Nom vide | POST `{"name":"","price":"9.90","stock":1}` | 422, `VALIDATION_FAILED` |
| Stock négatif | POST avec `"stock":-1` | 422 |
| Prix de 3 décimales | POST avec `"price":"12.345"` | 422 |
| Injecter un ID | POST avec `"id":999` en plus | 422, `UNKNOWN_FIELDS` |
| Mauvais type | POST avec `"stock":"texte"` | 400, `INVALID_TYPES` (selon Serializer) |
| JSON non objet | POST avec `[]` | 400, `INVALID_JSON_OBJECT` |
| Champ obligatoire omis | POST sans `stock` | 422, `MISSING_FIELDS` |
| PUT complet | PUT avec 4 champs requis | 200, valeurs remplacées |
| PUT incomplet | PUT sans `isActive` | 422, `MISSING_FIELDS` |
| PATCH partiel | PATCH `{"stock":0}` | 200, seul stock modifié |
| PATCH description effacée | PATCH `{"description":null}` | 200, description `null` |
| PATCH null interdit | PATCH `{"name":null}` | 422, `NULL_NOT_ALLOWED` |
| PATCH vide | PATCH `{}` | 422, `EMPTY_PATCH` |
| Supprimer | DELETE ID existant | 204, aucun contenu |
| Revérifier la suppression | GET ID supprimé | 404 |

**Conseil :** tester les cas d'échec en premier pour ne pas polluer la base, puis créer/modifier/supprimer un produit d'essai. Les changements sont bien réels dans la BDD. Utiliser un ID découvert dynamiquement, jamais supposer que c'est 1.

### 9.9 Exemple complet de POST final hors Swagger

```bash
curl -i -X POST http://127.0.0.1:8000/api/final/products \
  -H 'Content-Type: application/json' \
  -d '{"name":"Support écran","description":null,"price":"32.50","stock":9,"isActive":true}'
```

Vérifier que l'en-tête `Location` cible `/api/final/products/{id}`.

---

## 10. Pièges et choix d'architecture

### `json($entity)` n'est pas un contrat

Retourner une entité Doctrine directement peut exposer trop de champs, créer des problèmes de relations circulaires ou rendre l'API dépendante de la BDD. Les groupes aident, mais le DTO de sortie exprime plus clairement la frontière.

### DTO ≠ Validator ≠ Serializer

- **DTO** : structure des données transférées, sans garantie intrinsèque de validité ;
- **Serializer** : transforme JSON ⇄ objet (et vice-versa), avec ses règles de conversion ;
- **Validator** : évalue les contraintes définies sur l'objet ;
- **Entity** : représente le modèle persisté par Doctrine ;
- **Repository** : lit les entités ;
- **Service** : contient l'action applicative, notamment les écritures ici ;
- **Controller** : fait le lien HTTP avec l'application.

### Pourquoi ne pas tout faire avec `MapRequestPayload` ?

C'est un excellent outil, mais le tutoriel cherche à comprendre les étapes qu'il automatise. Dans la version finale, la lecture explicite permet de normaliser localement les réponses d'erreur. **Ce n'est pas une règle disant que MapRequestPayload serait mauvais en production.** Un autre projet pourrait choisir `MapRequestPayload` + gestionnaire d'exception central.

### Pourquoi pas de pagination, filtre complexe ou tri dans la version finale ?

Le contrat demandé porte sur un CRUD de démonstration court. Le cours explique aussi pagination, filtrage et tri, mais ils ne sont pas requis pour ce besoin. L'étape 12 montre le principe d'un filtre explicitement autorisé. Si une vraie liste devient volumineuse, prévoir pagination **dans le repository**, pas `array_slice($repository->findAll())`.

### Pourquoi pas de rôle, JWT, CORS ou droits dans les méthodes ?

Le cours rappelle que la sécurité doit toujours être contrôlée **côté serveur**, jamais simplement par le frontend. Mais cet atelier ne définit aucun utilisateur, aucune règle d'accès ni politique d'authentification. Inventer une protection `ROLE_ADMIN` empêcherait les tests immédiats sans configuration. **Ne pas déployer ces routes d'écriture accessibles publiquement.** Pour la vraie application, définir les droits par endpoint et par ressource, puis implémenter `#[IsGranted]`, voters ou services d'autorisation, configurer CORS selon les domaines autorisés et documenter la sécurité OpenAPI conformément à la réalité.

### OpenAPI n'est pas une garantie de conformité automatique

Un `OA\Response(response: 422, ...)` **décrit** la réponse, il ne l'implémente pas. Il faut garder code, documentation et scénarios de test alignés. Les schémas OpenAPI d'entrée ne sont pas des validateurs exécutés par le serveur ; ce sont les contraintes Symfony qui exécutent réellement les règles décrites dans les DTO.

### Pas d'appel à une API tierce ici

Le cinquième support du cours (« Consommer une API tierce ») couvre `HttpClient`, secrets, retry, timeout, webhooks et services dédiés. **Aucun de ces mécanismes n'est pertinent dans cet atelier CRUD local** : ne pas ajouter artificiellement un client HTTP externe à une API que nous sommes en train de construire.

### Tests manuels ≠ tests automatisés

Swagger UI permet de découvrir et déclencher des requêtes réelles. Ce n'est pas une suite répétable de régression. Pour plus tard : `WebTestCase`, cas positifs et négatifs, droits refusés, indépendance de la BDD de test. C'est enseigné dans le cours ; non ajouté ici pour garder le cheminement demandé.

---

## 11. Correspondance avec le cours

Les chapitres ci-dessous proviennent des **cinq fichiers HTML transmis**. Le tableau identifie les notions pertinentes ; les choix d'entité et de route restent ceux de cet atelier.

| Document fourni | Sections utilisées | Application dans l'atelier |
|---|---|---|
| *API : concepts et fonctionnement* | requêtes/réponses HTTP, méthodes, idempotence, codes, paramètres, body, validation, mass assignment | étapes 1 à 5, 11 à 12, erreurs finales |
| *OpenAPI : décrire et contractualiser une API* | `paths`, `requestBody`, `responses`, `components`, schémas entrée/sortie, erreurs, tags, synchronisation | étape 13, attributs du contrôleur final |
| *Swagger : exploiter une description OpenAPI* | Swagger UI, Try it out, limites des tests interactifs, sécurité, environnements | §3 et §9 |
| *Construire une API Symfony from scratch* | contrôleurs fins, repository, DTO, validation, service métier, sérialisation, Nelmio, gestion des erreurs, architecture cible | **référence principale** du code et de la progression |
| *Consommer une API tierce* | documentation des fournisseurs, HttpClient, secrets, timeouts, services dédiés | repère pour de futurs projets, non applicable au CRUD local |

Conformément au cours, il faut comprendre qu'une API commence par un **contrat**. Le contrôleur pédagogique fait volontairement l'inverse pendant les premières étapes pour visualiser les mécanismes. Le contrôleur final revient à une conception contractuelle et maintenable. Le résultat final reste une **application du cours à ce cas fictif**, pas une reproduction mot pour mot des exemples distribués.

### Ressources officielles utiles

- [Symfony — Contrôleurs](https://symfony.com/doc/7.4/controller.html)
- [Symfony — Serializer](https://symfony.com/doc/7.4/serializer.html)
- [Symfony — Validation](https://symfony.com/doc/7.4/validation.html)
- [Symfony — `MapRequestPayload`](https://symfony.com/doc/7.4/controller.html#mapping-request-payload)
- [NelmioApiDocBundle](https://symfony.com/bundles/NelmioApiDocBundle)
- [DoctrineFixturesBundle](https://symfony.com/doc/current/DoctrineFixturesBundle/index.html)
- [OpenAPI Specification](https://spec.openapis.org/oas/latest.html)

## 12. Vérifications et limites

- **Syntaxe PHP :** les fichiers fournis ont été soumis à `php -l` dans l'environnement de préparation.
- **Pas de test d'intégration exécuté :** le vrai projet Meditech Care, ses bundles installés, son container Symfony et sa BDD ne sont pas présents dans ce dossier ; vérifier les commandes après intégration.
- **Version :** le code vise Symfony 7 et une version de Nelmio compatible avec les attributs PHP 8 ; contrôler les versions dans `composer.lock` du vrai projet.
- **Fixture :** injection de 10 produits fixes, sans dépendance externe.
- **Production :** ni authentification, ni politique d'autorisation, ni normalisation globale des exceptions imprévues. Ne pas exposer ce laboratoire sur une instance publique.

### Checklist d'intégration

- [ ] Créer/adapter `Product` et `ProductRepository` sans conflit avec les classes déjà présentes.
- [ ] Copier les DTO et `ProductWriter` aux chemins exacts.
- [ ] Copier `ProductFixtures` et les deux contrôleurs.
- [ ] Vérifier les composants et la route Swagger UI existante.
- [ ] Générer, relire et exécuter la migration sur une base de développement.
- [ ] Charger **seulement** les produits de fixtures, avec `--group=ProductFixtures --append` si la base n'est pas jetable.
- [ ] Vérifier les routes avec `debug:router`.
- [ ] Parcourir les 13 étapes et tester les cas correspondants.
- [ ] Tester le CRUD final ainsi que ses erreurs et sa documentation OpenAPI.

**Résultat pédagogique attendu :** savoir expliquer, sans le réciter par cœur, le passage d'un `return $this->json(...)` simple à une API dont les entrées, sorties, erreurs, règles et documentation ont chacun une responsabilité identifiable.
