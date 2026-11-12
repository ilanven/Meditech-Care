# Atelier API Product — apprendre Symfony, OpenAPI et la validation en construisant 4 CRUD

> **Projet :** Meditech Care — atelier pédagogique BTS SIO SLAM.  
> **Pile du dépôt au moment de cet atelier :** PHP **8.4 minimum**, Symfony **8.1**, Doctrine ORM, Symfony Serializer, Symfony Validator, NelmioApiDocBundle et Swagger UI.  
> **Principe :** un même objet métier `Product`, une même base, **quatre manières de réaliser les six opérations d'un CRUD**.  
> **Outil de test principal :** Swagger UI (`/api/doc` si la configuration actuelle du projet est conservée).  
> **Méthode de travail :** lire une explication, ouvrir le code concerné, formuler une hypothèse, lancer une requête, examiner sa réponse, puis comprendre *pourquoi* elle est obtenue.

## Sommaire

1. [Comment utiliser cet atelier](#1-comment-utiliser-cet-atelier)
2. [Préparer le projet et les tests](#2-préparer-le-projet-et-les-tests)
3. [Le parcours d'une requête HTTP](#3-le-parcours-dune-requête-http)
4. [Le modèle Product et ses fichiers](#4-le-modèle-product-et-ses-fichiers)
5. [Niveau 1 — BASIC : construire un CRUD direct](#5-niveau-1--basic--construire-un-crud-direct)
6. [Niveau 2 — TAGOA : construire la documentation OpenAPI](#6-niveau-2--tagoa--construire-la-documentation-openapi)
7. [Niveau 3 — VERIFIED : contrôler les données](#7-niveau-3--verified--contrôler-les-données)
8. [Niveau 4 — FINAL : réunir les techniques](#8-niveau-4--final--réunir-les-techniques)
9. [Parcours de tests complets dans Swagger](#9-parcours-de-tests-complets-dans-swagger)
10. [Comprendre chaque fichier et chaque responsabilité](#10-comprendre-chaque-fichier-et-chaque-responsabilité)
11. [Erreurs fréquentes et diagnostic](#11-erreurs-fréquentes-et-diagnostic)
12. [Synthèse, vocabulaire et suite du cours](#12-synthèse-vocabulaire-et-suite-du-cours)

---

# 1. Comment utiliser cet atelier

## 1.1 Ce que l'on cherche réellement à comprendre

Un CRUD ne constitue pas seulement une succession de six méthodes PHP. Dans une API, les mêmes actions font intervenir **plusieurs responsabilités**, chacune avec un rôle distinct :

- **HTTP** transporte une requête et une réponse. Un client demande une opération : méthode, URL, en-têtes et éventuellement corps JSON.
- **Symfony Routing** décide quel contrôleur correspond à cette requête.
- **Le contrôleur** interprète la demande et coordonne les autres composants.
- **Doctrine** permet de lire et de modifier les objets persistés en base.
- **Le Serializer** convertit des données entre tableaux/objets PHP et représentations JSON.
- **Un DTO** définit explicitement les données d'entrée ou de sortie d'une opération.
- **Le Validator** applique des contraintes à ces objets.
- **OpenAPI** décrit le contrat auquel un client peut se fier ; **Swagger UI** permet de le parcourir et de lancer des requêtes.

L'idée du parcours est d'ajouter ces couches **une à une**, sur de véritables `GET`, `POST`, `PUT`, `PATCH` et `DELETE`, au lieu de découvrir des mécanismes sans rapport avec une opération métier.

## 1.2 Les quatre niveaux

| Niveau | Préfixe d'URL | À observer en priorité |
|---|---|---|
| **1 — Basic** | `/api/learning/products/basic` | Routes, `Request`, tableaux, repository, entités, `persist()`, `flush()` |
| **2 — TagOA** | `/api/learning/products/tagOA` | `#[OA\...]`, contrat, paramètres, corps de requête, schémas, exemples, statuts |
| **3 — Verified** | `/api/learning/products/verified` | Serializer, groupes, DTO, `Assert`, Validator, `MapRequestPayload`, PATCH |
| **4 — Final** | `/api/final/products` | Choix d'architecture, erreurs homogènes, types contrôlés, service, OpenAPI complet |

**Les six opérations se répètent dans chacun des quatre niveaux.** Par exemple, on peut comparer quatre POST qui créent tous un produit, mais avec des techniques progressivement différentes.

| Opération | Collection ou ressource | Réponse réussie |
|---|---|---|
| `GET` collection | URL de base | `200 OK` — tableau d'objets |
| `GET` unitaire | URL de base + `/{id}` | `200 OK` — objet |
| `POST` | URL de base | `201 Created` |
| `PUT` | URL de base + `/{id}` | `200 OK` |
| `PATCH` | URL de base + `/{id}` | `200 OK` |
| `DELETE` | URL de base + `/{id}` | `204 No Content` |

## 1.3 Une règle de lecture

À chaque étape, distinguer trois questions :

1. **Qu'est-ce qui est réellement exécuté ?** C'est le PHP du contrôleur, les composants Symfony et la base de données.
2. **Qu'est-ce qui est seulement décrit ?** Ce sont les attributs `#[OA\...]` et le contrat OpenAPI.
3. **Qu'est-ce qui est observé côté client ?** Ce sont le statut HTTP, les en-têtes et le corps présentés dans Swagger.

Exemple : déclarer `minimum: 0` sur un `OA\Property` *décrit* un stock attendu supérieur ou égal à zéro. Cela ne crée pas automatiquement un contrôle dans PHP. À l'inverse, `#[Assert\PositiveOrZero]` est une contrainte que le Validator peut réellement exécuter **si on le lui demande**.

## 1.4 Mode d'emploi des exercices

Chaque exercice contient quatre parties :

- **Avant l'exécution** : ce que l'on envoie et ce qui devrait se produire.
- **Dans Swagger** : la manipulation à effectuer.
- **Résultat attendu** : code et contenu les plus utiles à contrôler.
- **Interprétation** : pourquoi Symfony produit ce résultat et ce que cela enseigne.

> Les identifiants `1`, `5`, `42` montrés dans les exemples sont **fictifs**. Ils ne sont jamais garantis dans ta base. Pour les tests qui modifient les données, utiliser un produit créé au début de l'exercice, relever son ID réel et remplacer le paramètre `id` dans Swagger.

---

# 2. Préparer le projet et les tests

## 2.1 Fichiers du ZIP

Le ZIP contient les fichiers à remplacer, **avec leurs chemins d'origine** :

```text
src/
├── Controller/Api/
│   ├── ProductController.php          # Basic + TagOA + Verified
│   └── ProductFinalController.php     # version finale
├── Dto/Product/
│   ├── ApiErrorOutput.php
│   ├── CreateProductInput.php
│   ├── PatchProductInput.php
│   ├── ProductOutput.php
│   └── ReplaceProductInput.php
└── Service/
    └── ProductWriter.php

doc/
└── ATELIER_API_PRODUCT.md
```

Les fichiers suivants existent déjà dans le projet et sont **conservés tels quels** :

- `src/Entity/Product.php`, avec les propriétés Doctrine et les groupes `product:read` ;
- `src/Repository/ProductRepository.php`, repository Doctrine ;
- `src/DataFixtures/ProductFixtures.php`, jeu de dix produits de démonstration ;
- `config/packages/nelmio_api_doc.yaml` et la route Swagger déjà en place.

La structure de l'entité n'a pas changé : **aucune nouvelle migration n'est nécessaire pour cette refonte seule**.

## 2.2 Vérifications de départ

Depuis la racine du backend Symfony :

```bash
php -v
php bin/console about
php bin/console debug:router | grep -E 'api_(learning|final)_products'
```

Le fichier `composer.json` de la branche cible **PHP >= 8.4** et **Symfony 8.1**. Il déclare déjà Serializer, Validator, Doctrine, Nelmio et les fixtures. Ne pas réinstaller les dépendances qui sont déjà présentes.

**Après remplacement**, `debug:router` doit montrer :

- `api_learning_products_basic_index`, `..._basic_show`, `..._basic_create`, `..._basic_replace`, `..._basic_patch`, `..._basic_delete` ;
- la même série pour `tagoa_` ;
- la même série pour `verified_` ;
- les six routes `api_final_products_*`.

Les anciens endpoints `/api/learning/products/demo/...` **n'existent plus** ; ce n'est pas une régression, c'est le changement de parcours décidé pour cet atelier.

## 2.3 Préparer un jeu de données sans effacer Meditech Care

`ProductFixtures.php` prépare dix produits (clavier, souris, écran, etc.) ; un tableau fixe permet de comparer les réponses d'un lancement à l'autre.

**Attention à la commande des fixtures** :

```bash
# Simulation, sans modification de la base :
php bin/console doctrine:fixtures:load --group=ProductFixtures --append --dry-run

# Ajouter le jeu de produits sans purger les tables existantes :
php bin/console doctrine:fixtures:load --group=ProductFixtures --append
```

`--group=ProductFixtures` **sélectionne** les fixtures exécutées ; il ne désactive pas la purge. C'est `--append` qui empêche la suppression préalable des données. Relancer la seconde commande ajoute de nouveaux produits : elle ne met pas à jour ceux qui existent déjà.

Utiliser une **base de développement dédiée** est préférable. Les requêtes de Swagger sont réelles : `POST` ajoute des lignes et `DELETE` en retire. Elles ne s'exécutent pas dans un simulateur.

## 2.4 Ouvrir Swagger et savoir lire l'écran

1. Démarrer le serveur Symfony habituel.
2. Ouvrir `/api/doc` (ou l'adresse déjà prévue dans le projet).
3. Repérer les groupes **Products / 1 — Basic**, **Products / 2 — TagOA**, **Products / 3 — Verified**, **Products / 4 — Final**.
4. Déplier une opération, cliquer **Try it out**, compléter les champs, puis cliquer **Execute**.
5. Examiner surtout **Request URL**, **Server response**, **Response code**, **Response body** et **Response headers**.

Ces zones ne répondent pas toutes à la même question :

| Zone Swagger | Signification |
|---|---|
| **Parameters** | Valeurs placées dans l'URL, par exemple le `{id}` d'un produit |
| **Request body** | JSON que le client envoie au serveur |
| **Example Value / Schema** | Exemple ou schéma *documenté* par OpenAPI, pas le résultat d'un appel |
| **Request URL** | Adresse effectivement appelée |
| **Response code** | Statut HTTP réellement retourné par cette exécution |
| **Response body** | Contenu réellement renvoyé par le serveur |
| **Response headers** | Métadonnées HTTP ; rechercher `content-type` ou `location` |

**Ne pas confondre une réponse documentée `201` avec une réponse effectivement reçue `201`.** Si une exécution renvoie `500`, la documentation affichée ne fait pas disparaître cette erreur : elle indique simplement ce que le contrat prévoyait.

## 2.5 Garder une trace des tests

Noter, pour le produit créé en Basic :

```text
ID créé : ..................
Nom initial : ..............
Prix initial : .............
Stock initial : ............
```

Le même ID peut être consulté par toutes les versions du CRUD : elles lisent la **même table `product`**. Cela permet de comparer des réponses différentes à partir d'un état métier identique.

---

# 3. Le parcours d'une requête HTTP

## 3.1 Anatomie d’une requête

Quand le client envoie :

```http
POST /api/learning/products/basic HTTP/1.1
Content-Type: application/json

{"name":"Lampe USB","price":"18.90","stock":5}
```

Il faut distinguer :

- `POST` : **méthode HTTP**, donc l'action demandée ;
- `/api/learning/products/basic` : **chemin** utilisé par le routeur ;
- `Content-Type: application/json` : déclaration du **format du contenu envoyé** ;
- le texte entre accolades : **body**, c'est-à-dire le JSON envoyé ;
- `name`, `price`, `stock` : **clés du body**, et non paramètres de route.

Le serveur peut répondre :

```http
HTTP/1.1 201 Created
Content-Type: application/json

{"id":42,"message":"Créé"}
```

Le **statut `201`** exprime le résultat de l'opération ; le **body** peut contenir les données du résultat. Deux requêtes identiques peuvent produire des ID différents, puisque `POST` crée une nouvelle ressource à chaque exécution réussie.

## 3.2 La route Symfony

Lire cette ligne dans `ProductController.php` :

```php
#[Route('/basic/{id}', name: 'basic_show', requirements: ['id' => '\d+'], methods: ['GET'])]
```

Elle se trouve dans une classe elle-même décorée par :

```php
#[Route('/api/learning/products', name: 'api_learning_products_', format: 'json')]
```

Symfony compose les deux attributs :

- **Chemin final :** `/api/learning/products/basic/{id}` ;
- **Nom de route final :** `api_learning_products_basic_show` ;
- **Méthode acceptée :** `GET` ;
- **Format de réponse déclaré :** `json` (utile notamment pour les erreurs Symfony) ;
- **Condition sur `{id}` :** doit correspondre à l'expression régulière `\d+`.

### Décomposer `requirements: ['id' => '\d+']`

| Morceau | Ce que cela signifie |
|---|---|
| `requirements` | Option de `#[Route]` fixant des conditions pour les variables du chemin |
| `[...]` | Tableau associatif PHP |
| `'id'` | Nom du paramètre de route à contrôler ; il correspond à `{id}` |
| `=>` | Sépare la clé de sa valeur dans le tableau PHP |
| `'\d+'` | Expression régulière : `\d` = chiffre ; `+` = au moins une occurrence |

Ainsi, `/basic/42` correspond à la route, mais `/basic/abc` ne lui correspond pas. **Une URL non reconnue provoque un 404 au niveau du routeur**, avant même d'entrer dans `basicShow()`.

Autre subtilité : `\d+` accepte également `0` et les nombres avec plusieurs chiffres. Il garantit une *forme*, pas la présence d'une ligne en base. `getId()` ou `Repository::find()` ne sont pas exécutés tant que la route n'a pas été reconnue.

**À tester :** dans la barre d'adresse, appeler `/api/learning/products/basic/abc`, puis `/api/learning/products/basic/999999999`. Le premier est rejeté par le routing ; le second passe le routing, puis est rejeté par le contrôleur si aucun produit ne possède cet ID. Les deux peuvent afficher `404`, mais **leur origine est différente**.

### Pourquoi `methods: ['GET']` est important

Sans restriction de méthode, une route peut être sélectionnée pour d'autres verbes HTTP selon sa configuration. Ici, on exige expressément `GET`. Symfony distinguera donc les six actions même lorsque leurs URL sont identiques : `GET /basic`, `POST /basic` sont deux routes différentes. Le verbe fait partie du contrat de l'API.

## 3.3 Injection de dépendances et autowiring

Lire la signature :

```php
public function basicIndex(ProductRepository $repository): JsonResponse
```

Il n'existe aucun `new ProductRepository()` dans la méthode. Symfony fournit automatiquement l'objet compatible avec le type demandé : c'est l'**injection de dépendances**. Ce mécanisme s'appuie sur le conteneur de services et l'autowiring.

On retrouve le même principe avec :

```php
public function basicCreate(Request $request, EntityManagerInterface $em): JsonResponse
```

Ici, la requête courante et l'EntityManager sont fournis par Symfony. Plus tard, la même syntaxe permettra d'injecter `SerializerInterface`, `ValidatorInterface` et `ProductWriter`.

Il faut distinguer **une dépendance de service** (`ProductRepository`, par exemple) d'une **valeur extraite de la route** (`int $id`). Ce sont deux origines différentes, même si elles arrivent toutes deux sous forme d'arguments PHP.

## 3.4 Le rôle des codes HTTP

| Code | À comprendre | Où l'observer |
|---|---|---|
| `200 OK` | La lecture ou la modification a abouti | `GET`, `PUT`, `PATCH` |
| `201 Created` | Une ressource vient d'être créée | `POST` |
| `204 No Content` | L'opération est réussie et **ne retourne pas de body** | `DELETE` |
| `400 Bad Request` | JSON illisible, racine incorrecte ou mauvais type selon le contrat final | `POST`, `PUT`, `PATCH` invalides |
| `404 Not Found` | La route ne correspond pas ou la ressource demandée n'existe pas | `GET /{id}` inexistant |
| `415 Unsupported Media Type` | Le corps de requête n'est pas fourni dans le format accepté | `Content-Type` incorrect |
| `422 Unprocessable Content` | JSON compris, mais exigences sur ses champs/valeurs non respectées | Validation `Assert`, champ manquant/inconnu |
| `500 Internal Server Error` | Le serveur a échoué de manière non prévue | Possible dans Basic/TagOA sur entrée invalide |

**Dans cet atelier :** `400`, `415`, `422` ont des comportements volontairement plus stricts dans la version finale que dans Basic. Ne pas supposer que toute mauvaise requête possède déjà une gestion d'erreur propre dans les niveaux d'introduction.

## 3.5 PUT et PATCH ne sont pas synonymes

Avec un produit initial :

```json
{"id":42,"name":"Lampe USB","description":"Pour bureau","price":"18.90","stock":5,"isActive":true}
```

Envoyer en **PUT** un nouvel état complet peut remplacer le nom, le prix, le stock, le statut et la description. Dans ce modèle, omettre la description en PUT revient à l'effacer (`null`).

Envoyer en **PATCH** :

```json
{"stock":3}
```

ne doit modifier que `stock`. Envoyer ensuite :

```json
{"description":null}
```

ne touche pas aux autres champs, mais remet explicitement `description` à `null`. C'est pourquoi **présence d'une clé** et **valeur de cette clé** seront deux notions distinctes au niveau Verified.

Autre distinction : répéter un même PUT avec les mêmes données doit conduire au même état final ; un PATCH de type « assigner stock = 3 » aussi. Mais un PATCH d'une autre forme, par exemple « incrémenter de 3 », pourrait ne pas être idempotent. Le verbe seul ne garantit donc pas cette propriété pour PATCH.

---

# 4. Le modèle Product et ses fichiers

## 4.1 Les propriétés de Product

Ouvrir `src/Entity/Product.php` :

| Champ | Type PHP / Doctrine | Ce qu'il représente |
|---|---|---|
| `id` | `?int` / identifiant généré | Créé par la base, jamais envoyé en création |
| `name` | `?string` / `VARCHAR(150)` | Nom du produit |
| `description` | `?string` / texte nullable | Description pouvant être absente |
| `price` | `?string` / `DECIMAL(10,2)` | Prix représenté par une chaîne |
| `stock` | `?int` / entier | Quantité en stock |
| `isActive` | `bool` / booléen | Produit actif ou inactif |

Dans Doctrine, une entité est un **objet représentant une ligne** de base de données. `Product` contient les propriétés et les méthodes permettant de les lire (`getName()`) ou de les modifier (`setName()`).

### Pourquoi certains getters retournent-ils `?string` ?

Le point d'interrogation signifie « `string` ou `null` ». Une entité tout juste instanciée peut ne pas encore être remplie ; son ID est aussi `null` avant insertion. Le fait qu'une colonne soit déclarée non-nullable en base ne change pas automatiquement cette possibilité pendant la vie de l'objet PHP.

La version finale impose que les valeurs de sortie attendues existent, au moment de construire `ProductOutput`. Cette vérification évite de transmettre silencieusement un objet incomplet à une réponse publique.

### Pourquoi un `price` de type `string` ?

Doctrine stocke un `DECIMAL` sans recourir au flottant binaire. DBAL le manipule en chaîne, par exemple `"49.90"`. En PHP, un `float` représente certains décimaux de façon approximative ; pour un prix il est préférable de conserver une représentation exacte. C'est aussi pourquoi les JSON d'essai contiennent **`"price":"49.90"`**, avec des guillemets, et non `"price":49.90`.

### Qu'est-ce que `#[Groups(['product:read'])]` ?

Cet attribut, déjà présent sur `Product.php`, est lu par le **Serializer** lorsqu'il reçoit le contexte `['groups' => ['product:read']]`. Seules les propriétés appartenant au groupe demandé sont sélectionnées. Ce n'est pas une règle de sécurité d'accès aux routes ; cela contrôle la **forme des données sérialisées**.

## 4.2 Repository, EntityManager et ProductWriter

- `ProductRepository::find($id)` recherche une seule entité par clé primaire ; si elle n'existe pas, le résultat est `null`.
- `ProductRepository::findBy([], ['id' => 'ASC'])` lit une collection, sans critère de filtrage, triée sur `id` croissant.
- `EntityManagerInterface::persist($entity)` enregistre une **intention** de persistance pour une nouvelle entité ; ce n'est pas encore une insertion SQL.
- `EntityManagerInterface::flush()` synchronise les modifications suivies avec la base de données.
- `EntityManagerInterface::remove($entity)` programme une suppression, ensuite exécutée par `flush()`.
- `ProductWriter` regroupe les actions d'écriture dans Verified et Final pour que le contrôleur ne contienne pas les détails Doctrine.

**Pourquoi pas `persist()` dans PUT ?** L'entité a été chargée par Doctrine et elle est déjà suivie. Modifier ses propriétés puis exécuter `flush()` suffit généralement. En POST, l'objet est neuf, d'où le `persist()` initial.

## 4.3 Visualiser l'architecture avant de coder

```text
Client (Swagger / frontend)
    |
    | Requête HTTP avec éventuellement un body JSON
    v
Symfony Router
    |
    v
ProductController / ProductFinalController
    |                       |
    | GET                   | POST / PUT / PATCH / DELETE
    v                       v
ProductRepository       ProductWriter (Verified et Final)
    |                       |
    +----------+------------+
               |
               v
         Doctrine ORM
               |
               v
           Base SQL
```

Le rôle du Serializer, des DTO et du Validator sera ajouté à ce schéma au **niveau 3**. On commence volontairement avec une architecture visible et courte.

---
# 5. Niveau 1 — BASIC : construire un CRUD direct

**Code à ouvrir :** `ProductController.php`, bloc `NIVEAU 1 — BASIC`.  
**Groupe Swagger :** `Products / 1 — Basic`.  
**Base des URL :** `/api/learning/products/basic`.

Dans cette partie, une requête correcte doit fonctionner, mais les entrées ne sont **pas contrôlées** par des DTO ni par le Validator. Il faut donc commencer par des JSON valides. Les quelques attributs `OA` des POST/PUT/PATCH servent à faire apparaître l'éditeur de body dans Swagger ; ils ne transforment pas le niveau Basic en API validée.

## 5.1 GET collection : première réponse JSON

**À lire :** méthode `basicIndex()`.

```php
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
    return $this->json($result);
}
```

**Décryptage, instruction par instruction :**

1. Le repository est injecté : le contrôleur n'a pas besoin de le construire.
2. `findBy([], ['id' => 'ASC'])` interroge la BDD ; `[]` signifie aucun filtre ; `ASC` signifie tri croissant.
3. `foreach` parcourt les entités `Product` retournées.
4. `$result[] = [...]` ajoute un tableau associatif PHP à la collection, à chaque passage.
5. `$this->json($result)` encode la collection PHP en une réponse HTTP JSON ; le statut par défaut est `200`.

**Test guidé :** dans Swagger, ouvrir le GET `/api/learning/products/basic`, cliquer sur **Try it out**, puis **Execute**. Aucun paramètre ni body n'est nécessaire.

**Résultat attendu :** `200`, un tableau JSON `[...]`, avec un objet par produit. Si aucune fixture n'est chargée et qu'aucun produit n'existe, `[]` est un résultat parfaitement normal. Relever l'ID d'un produit.

**Ce que cela démontre :** les données envoyées à un client sont celles que le contrôleur a choisies. La table contient des objets Doctrine, mais HTTP transporte du JSON, pas des entités PHP.

## 5.2 GET unitaire : route dynamique, recherche et 404

**À lire :** méthode `basicShow()`.

```php
$product = $repository->find($id);
if ($product === null) {
    return $this->json(['message' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
}
```

`find($id)` retourne **un objet Product ou `null`**. On doit donc tester `null` avant de demander `getName()` ou `getPrice()`. La constante `Response::HTTP_NOT_FOUND` vaut `404` ; l'utiliser est équivalent à écrire `404`, mais rend le sens plus évident.

**Test A — produit existant :** ouvrir GET `/basic/{id}`, saisir l'ID relevé à l'étape précédente et exécuter. Attendre `200` et un objet JSON avec `id`, `name`, `description`, `price`, `stock`, `isActive`.

**Test B — produit absent :** saisir `999999999`, puis exécuter. Attendre `404` avec :

```json
{"message":"Produit introuvable."}
```

**Test C — URL invalide :** essayer l'URL terminée par `/abc` directement dans le navigateur. Attendre un `404` dû au routeur ; ce cas est différent de l'absence d'une ligne en base.

**À retenir :** la validité syntaxique d'une URL, le type PHP `int` et l'existence réelle d'une entité sont **trois questions différentes**.

## 5.3 POST : lire le body, construire l'entité, enregistrer

**À lire :** `basicCreate()`. Les nouveaux outils sont :

```php
$data = $request->toArray();
$product = new Product();
$product->setName($data['name']);
// ... autres setters
$em->persist($product);
$em->flush();
return $this->json(['id' => $product->getId(), 'message' => 'Créé'], 201);
```

- `Request $request` représente la requête entrante et donne accès à ses en-têtes, paramètres et body.
- `toArray()` décode son contenu JSON en tableau PHP. Cela **ne valide pas** la longueur du nom ni le caractère positif du stock.
- `$data['name']` accède à la clé `name` du tableau. Si cette clé n'existe pas, ce niveau ne sait pas encore répondre proprement.
- `new Product()` instancie un nouvel objet qui n'a pas encore d'ID de base de données.
- Les setters renseignent l'entité.
- `persist()` puis `flush()` provoquent la création de la ligne SQL et la génération de l'ID.
- `201` signifie que le serveur a créé une ressource, non qu'il s'est simplement contenté de lire une donnée.

**Test guidé :** ouvrir POST `/basic`, cliquer **Try it out** et remplacer le contenu par :

```json
{
  "name": "Lampe USB Basic",
  "description": "Produit créé pour le premier atelier",
  "price": "18.90",
  "stock": 5,
  "isActive": true
}
```

Exécuter. Résultat attendu : `201`, puis un body ressemblant à :

```json
{"id":42,"message":"Créé"}
```

L'ID est choisi par la base. **Le noter**, puis exécuter GET `/basic/{id}` pour vérifier que le produit existe et que les valeurs correspondent au POST.

**Pourquoi `price` possède-t-il des guillemets, mais pas `stock` ?** `price` doit être une chaîne décimale et `stock` un entier JSON. `"5"` et `5` ne sont pas la même chose en JSON, même si PHP peut parfois convertir des types.

**Limite volontaire :** envoyer `{}` ou un type inattendu peut aboutir à une exception et à un `500`. Ce résultat illustre un défaut de validation : ne pas le considérer comme un comportement correct d'une API réelle. L'atelier Verified permettra d'y répondre.

## 5.4 PUT : remplacer un état complet

**À lire :** `basicReplace()`.

Ici, la ressource existe déjà, donc on commence par `find($id)`. On utilise ensuite des setters, exactement comme en création. La différence se trouve à la fin : `flush()` suffit parce que Doctrine suit déjà l'entité chargée.

**Test guidé :** ouvrir PUT `/basic/{id}`, saisir **l'ID du produit créé en 5.3**, puis envoyer :

```json
{
  "name": "Lampe USB Basic — nouvelle version",
  "description": "Remplacée par PUT",
  "price": "21.90",
  "stock": 8,
  "isActive": false
}
```

Résultat attendu : `200`, un petit JSON avec l'ID et le message `Remplacé`.

**Important :** cela ne signifie pas que le GET retournera ce même petit JSON. Chaque endpoint décide de la **forme de sa réponse**. Faire immédiatement un GET `/basic/{id}` pour vérifier le nouvel état, notamment `isActive: false`.

**Second test — différence PUT/PATCH :** renvoyer le même PUT sans la propriété `description`, en conservant les quatre autres propriétés nécessaires. Attendre `200` puis vérifier que `description` est maintenant `null`. Le contrôleur utilise en effet :

```php
$product->setDescription($data['description'] ?? null);
```

`??` utilise la valeur à droite si la valeur de gauche est absente ou `null`. Dans ce PUT, l'absence d'une description revient donc à l'effacer.

## 5.5 PATCH : modifier certains champs sans toucher aux autres

**À lire :** `basicPatch()`.

```php
if (array_key_exists('stock', $data)) {
    $product->setStock($data['stock']);
}
```

`array_key_exists()` répond à la question « cette clé est-elle présente ? », **indépendamment de la valeur associée**. C'est différent de `isset()`, qui renvoie `false` si la clé existe mais possède la valeur `null`.

**Test A — modifier seulement le stock :** PATCH `/basic/{id}`, body :

```json
{"stock":3}
```

Attendre `200`, puis refaire GET `/basic/{id}`. Le stock doit valoir `3` et **le nom, le prix et le statut doivent rester inchangés**.

**Test B — effacer une description :** PATCH sur le même produit avec :

```json
{"description":null}
```

Attendre `200`. GET doit montrer `"description": null` et conserver les autres champs. Le code fonctionne parce que `array_key_exists('description', $data)` retourne `true`, même lorsque la valeur est `null`.

**Test C — comparer avec une clé absente :** envoyer uniquement `{"stock":4}`. La description doit conserver sa valeur actuelle. Le service ne reçoit aucune instruction de la modifier.

**Limite :** Basic peut échouer si une clé présente contient un type incompatible. C'est normal pour le niveau d'introduction, pas acceptable comme validation finale.

## 5.6 DELETE : la réponse vide

**À lire :** `basicDelete()`.

```php
$em->remove($product);
$em->flush();
return new Response(status: Response::HTTP_NO_CONTENT);
```

**Test guidé :** utiliser DELETE `/basic/{id}` pour le produit créé au début de l'exercice.

Résultat attendu : `204`. Il peut ne rien y avoir dans la section **Response body** : **c'est exactement le comportement recherché**. Il ne faut pas ajouter `{"message":"Supprimé"}` à une réponse `204`.

Ensuite, exécuter GET `/basic/{id}` sur le même identifiant. Attendre `404`. La ligne n'est plus trouvable.

### Bilan du niveau Basic

À ce stade, il faut être capable d'expliquer de mémoire :

- pourquoi GET collection retourne un tableau et GET unitaire un objet ;
- comment `{id}` devient un argument `int $id` ;
- ce qui différencie `persist()` de `flush()` ;
- pourquoi PUT remplace et PATCH conserve les champs absents ;
- pourquoi `404` et `204` ont des significations différentes ;
- pourquoi une entrée incorrecte peut encore faire échouer ce premier contrôleur.

La suite ne change pas le besoin métier. Elle s'intéresse à la **description de ce besoin pour le client**.

---

# 6. Niveau 2 — TAGOA : construire la documentation OpenAPI

**Code à ouvrir :** `ProductController.php`, bloc `NIVEAU 2 — TAG OA`, ainsi que les cinq `#[OA\Schema(...)]` placés au-dessus de la classe.  
**Groupe Swagger :** `Products / 2 — TagOA`.  
**Base des URL :** `/api/learning/products/tagOA`.

## 6.1 Le problème : le client ne connaît pas le PHP du serveur

Supposer que quelqu'un souhaite développer un frontend Svelte qui consomme notre API. Il lui faut savoir **avant même d'envoyer une requête** : quelles URL existent, quelles méthodes utiliser, quels champs envoyer, lesquels sont obligatoires, à quoi ressemblent les réponses et quels codes HTTP doivent être prévus.

Lire le PHP peut aider pendant le développement, mais un client externe ne doit pas avoir à explorer le code source du backend. On construit donc un **contrat d'API** : sa représentation standardisée est un document OpenAPI.

**Chaîne à distinguer :**

```text
Attributs #[OA\...] dans le PHP
             |
             v
NelmioApiDocBundle + swagger-php
             |
             v
Document OpenAPI (paths, schemas, responses...)
             |
             v
Swagger UI : affichage + possibilité d'envoyer des requêtes
             |
             v
Vraie requête HTTP vers le contrôleur Symfony
```

**Ni OpenAPI ni Swagger ne remplace le code de l'API.** Une description affichée ne prouve pas que le serveur respecte cette description. L'atelier Verified ajoutera les contrôles réellement exécutés.

## 6.2 `#[Route]` et `#[OA\Post]` : deux responsabilités

Comparer :

```php
#[Route('/tagOA', name: 'tagoa_create', methods: ['POST'])]
#[OA\Post(
    summary: 'Créer un produit — démonstration requestBody et 201',
    // ...
)]
```

- `#[Route]` : Symfony **enregistre une opération exécutable**. Il sélectionne la méthode PHP lorsque la requête correspond.
- `#[OA\Post]` : swagger-php **décrit l'opération** dans le contrat OpenAPI. Il ne crée pas la route et n'impose pas la validité des données.
- `OA` : alias PHP obtenu avec `use OpenApi\Attributes as OA;`. Grâce à lui, on écrit `OA\Post`, `OA\Response`, etc., plutôt que le namespace complet.
- `#[...]` : **attribut PHP**. C'est une métadonnée structurée, composée de vrais objets PHP ; ce n'est pas un simple commentaire libre.
- `new OA\RequestBody(...)` : crée un objet de description imbriqué dans `OA\Post`.

Les cours peuvent aussi présenter des annotations historiques `@OA\Post(...)` à l'intérieur de blocs PHPDoc. Ici, le dépôt utilise la **syntaxe moderne des attributs PHP**. Les deux visent le même standard OpenAPI mais n'ont pas la même syntaxe source.

## 6.3 Décomposer les attributs OpenAPI les plus importants

### `summary` — le titre visible

`summary` doit être **court, unique et orienté action** : « Créer un produit », « Rechercher un produit », « Supprimer un produit ». Il aide à identifier l'opération dans la liste de Swagger.

### `description` — expliquer le comportement observable

Contrairement à un commentaire interne sur un `foreach`, une `description` OpenAPI s'adresse au **consommateur de l'API**. Elle doit indiquer ce qu'il doit envoyer, ce qui se passera, les cas particuliers et les erreurs pertinentes. Elle ne doit pas inventer des garanties que le serveur ne possède pas.

Dans TagOA, les descriptions signalent explicitement que la validation métier n'est pas encore activée. Ce n'est pas seulement une précaution : un contrat qui annonce un rejet `422` sans code pour le produire est trompeur.

### `tags` — classer les opérations dans Swagger

```php
tags: ['Products / 2 — TagOA']
```

La valeur correspond au groupe dans lequel Swagger rassemble les routes. Elle **ne correspond pas** à un rôle utilisateur, à une permission, à un chemin et encore moins à une validation.

### `parameters` — ce qui vient de l'URL

```php
parameters: [
    new OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Identifiant du produit à consulter.',
        schema: new OA\Schema(type: 'integer', minimum: 1),
        example: 5,
    ),
],
```

Décomposer :

| Argument | Signification |
|---|---|
| `parameters` | Liste de paramètres décrits pour l'opération |
| `name: 'id'` | Nom du paramètre dans l'URL |
| `in: 'path'` | Indique que la valeur appartient au **chemin** (`/{id}`) |
| `required: true` | Le paramètre doit être fourni : indispensable pour un path parameter |
| `description` | Guide le client sur sa signification |
| `schema` | Définit le **type** et les contraintes documentées |
| `type: 'integer'` | Le client doit fournir un entier |
| `minimum: 1` | Le contrat décrit un identifiant strictement positif |
| `example: 5` | Propose une valeur représentative, pas un ID garanti en base |

Les autres valeurs de `in` possibles dans OpenAPI incluent notamment `query`, `header` et `cookie`. Elles désignent des emplacements distincts ; on n'utilise pas `requestBody` pour décrire un paramètre de chemin.

**Attention :** `requirements: ['id' => '\d+']` côté Symfony et `minimum: 1` dans OpenAPI sont deux déclarations indépendantes. La première reconnaît des chiffres, y compris zéro ; la seconde documente l'attente d'un identifiant positif. Si on voulait interdire zéro au niveau du routeur, il faudrait adapter sa règle de route. Dans notre exercice, `0` atteint le contrôleur puis donne généralement `404`.

### `requestBody` — ce que le client envoie comme JSON

```php
requestBody: new OA\RequestBody(
    required: true,
    description: 'Objet JSON à insérer.',
    content: new OA\JsonContent(
        ref: '#/components/schemas/LearningProductCreate',
        example: ['name' => 'Lampe USB', 'price' => '18.90', 'stock' => 5],
    ),
),
```

**Lecture précise :**

- `requestBody` est la description du contenu de la requête, généralement pour POST, PUT, PATCH.
- `required: true` **sur RequestBody** signifie que la requête doit contenir un body. Cela ne déclare pas que toutes les propriétés JSON sont obligatoires.
- `content` décrit le ou les formats acceptés.
- `OA\JsonContent` est une écriture abrégée du contenu au format **`application/json`**.
- `ref` renvoie vers un schéma réutilisable déjà défini dans `components/schemas`.
- `example` donne à Swagger un exemple de **body complet**.

La question des propriétés requises est traitée **à l'intérieur du schéma**, avec `required: ['name','price','stock']`. Il faut donc distinguer le body obligatoire de ses champs obligatoires.

### `responses` — ce que le serveur est censé renvoyer

```php
responses: [
    new OA\Response(
        response: 201,
        description: 'Produit créé.',
        content: new OA\JsonContent(/* ... */),
    ),
],
```

`response: 201` décrit un **statut HTTP**, pas un identifiant ni un code métier. Chaque réponse peut avoir son propre schéma. Un `201` peut renvoyer un objet créé ; un `404` peut renvoyer une erreur. Pour `204`, la réponse est vide : **ne pas décrire de `content` JSON**.

Une opération peut documenter plusieurs réponses : par exemple, `GET /{id}` prévoit `200` si le produit existe et `404` sinon. La réponse effectivement obtenue dépend des données et du code PHP.

## 6.4 Les schémas : décrire la structure des objets

En haut de `ProductController.php`, lire :

```php
#[OA\Schema(
    schema: 'LearningProductCreate',
    type: 'object',
    required: ['name', 'price', 'stock'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Clavier mécanique'),
        // ...
    ],
)]
```

Ce bloc déclare une **composante réutilisable** du contrat. Dans le document généré, elle appartient à `components.schemas.LearningProductCreate`. Elle peut ensuite être citée par plusieurs opérations grâce à :

```php
ref: '#/components/schemas/LearningProductCreate'
```

L'intérêt du `$ref` est d'éviter de recopier partout les mêmes descriptions. Cela n'introduit pas un DTO PHP : `LearningProductCreate` est ici **un nom de schéma OpenAPI** défini dans les attributs, pas une classe instanciée pour valider une requête.

Le contrôleur définit cinq schémas pour l'apprentissage :

| Schéma | Rôle |
|---|---|
| `LearningProduct` | Objet complet renvoyé à la lecture |
| `LearningProductCreate` | JSON attendu en POST |
| `LearningProductReplace` | JSON attendu en PUT |
| `LearningProductPatch` | Champs facultatifs du PATCH |
| `LearningProductNotFound` | Réponse d'erreur 404 des méthodes simples |

**Différence déterminante :** les schémas et les exemples sont des **objets de documentation**. Dans Verified et Final, des **DTO PHP** distincts serviront également à traiter et valider réellement les entrées.

### Décomposer `OA\Property`

Pour un stock :

```php
new OA\Property(property: 'stock', type: 'integer', minimum: 0, example: 12)
```

- `property` : nom du champ dans l'objet JSON ;
- `type` : type JSON attendu ;
- `minimum` : règle documentaire sur une valeur numérique ;
- `example` : valeur que Swagger peut proposer.

Pour une description :

```php
new OA\Property(property: 'description', type: 'string', nullable: true)
```

`nullable: true` indique qu'un **`null` explicite** est accepté selon ce schéma. Ce n'est pas la même chose que « le champ peut être omis » : c'est le tableau `required` du schéma qui décide si une propriété doit être présente.

Pour un prix, `type: 'string'` documente le contrat JSON avec guillemets ; il ne déclenche aucun cast PHP dans le contrôleur TagOA.

### Décomposer `OA\Items` — réponse contenant plusieurs produits

```php
new OA\JsonContent(
    type: 'array',
    items: new OA\Items(ref: '#/components/schemas/LearningProduct'),
)
```

- `type: 'array'` : le JSON de sortie est une liste `[...]` ;
- `items` : décrit la structure **d'un élément de cette liste** ;
- `ref` : chaque élément correspond au schéma d'un produit.

Ne pas confondre la collection `[{...},{...}]` et un objet `{...}` : c'est une distinction essentielle pour le frontend. Avec un mauvais schéma, Swagger pourrait afficher un seul objet alors que le serveur renvoie un tableau.

### Exemple vs contrainte

`example` ne limite pas les valeurs possibles ; `enum`, `minimum`, `maxLength`, `pattern`, `required` décrivent des contraintes. Mais **OpenAPI ne les applique pas automatiquement au backend**. Une contrainte n'est réellement évaluée qu'avec le code et les composants correspondants.

## 6.5 Exercice OpenAPI A — GET collection

**Objectif :** voir les effets d'une bonne documentation sans changement de logique PHP.

1. Déplier GET `/api/learning/products/tagOA` dans Swagger.
2. Lire le **summary** et la **description** de l'opération.
3. Dans les **Responses**, ouvrir le modèle prévu pour `200`.
4. Constater qu'il s'agit d'un `array` dont les éléments sont des `LearningProduct`.
5. Cliquer **Try it out** puis **Execute**.

**Résultat attendu :** statut `200` et même type de données que le GET Basic. Le JSON est toujours construit manuellement par le contrôleur.

**À comprendre :** l'intérêt d'OpenAPI est visible **avant l'exécution**. Il permet de prévoir le résultat, mais l'exécution elle-même est toujours réalisée par Symfony et Doctrine.

## 6.6 Exercice OpenAPI B — GET unitaire et id

1. Déplier GET `/tagOA/{id}`.
2. Vérifier que Swagger demande un paramètre `id` de type `integer` dans le **path**.
3. Saisir l'ID d'un produit connu et exécuter : attendre `200`, un objet produit.
4. Recommencer avec `999999999` : attendre `404`, un objet `{"message":"Produit introuvable."}`.
5. Revenir aux descriptions des deux réponses documentées : elles correspondent à deux scénarios distincts.

**À comprendre :** un seul endpoint possède plusieurs **issues HTTP**. Une documentation complète doit décrire les scénarios normaux *et* les scénarios d'absence de ressource.

## 6.7 Exercice OpenAPI C — POST et `requestBody`

1. Déplier POST `/tagOA` ; lire `Request body`.
2. Vérifier que Swagger propose le format `application/json`.
3. Examiner le schéma `LearningProductCreate` : trois champs marqués comme obligatoires.
4. Cliquer **Try it out** et envoyer :

```json
{
  "name": "Support portable TagOA",
  "description": "Exemple pour observer le contrat",
  "price": "34.90",
  "stock": 6,
  "isActive": true
}
```

5. Exécuter ; attendre `201`, `id` et `message`.
6. Observer que la réponse **réelle** est un petit objet contenant l'ID, non un `LearningProduct` complet. C'est bien ce qu'annonce la documentation de cette route.

**À comprendre :** `requestBody` décrit l'entrée ; `responses` décrit la sortie. Les deux JSON peuvent avoir des structures différentes sans que ce soit une anomalie.

## 6.8 Exercice OpenAPI D — PUT et PATCH

Pour PUT `/tagOA/{id}` :

1. Utiliser l'ID du produit créé en 6.7.
2. Observer le schéma `LearningProductReplace` : `name`, `price`, `stock`, `isActive` sont obligatoires.
3. Envoyer un état complet valide, puis vérifier le résultat avec GET `/tagOA/{id}`.

Pour PATCH `/tagOA/{id}` :

1. Observer le schéma `LearningProductPatch` : aucune propriété n'est obligatoirement présente dans le schéma.
2. Envoyer `{"stock":2}` : la réponse est `200`.
3. Faire un GET et vérifier que seul le stock a changé.
4. Envoyer `{"description":null}` : la réponse est `200` et la description devient `null`.

**À comprendre :** les deux routes manipulent la même entité, mais le **contrat d'entrée** n'est pas identique. C'est pourquoi les schémas `LearningProductReplace` et `LearningProductPatch` sont séparés.

## 6.9 Exercice OpenAPI E — DELETE et le cas 204

1. Ouvrir DELETE `/tagOA/{id}`.
2. Vérifier que la réponse `204` n'annonce **aucun objet JSON**.
3. Supprimer le produit créé en 6.7.
4. Constater `204` et l'absence de body.
5. Refaire un GET sur le même ID : `404`.

**À comprendre :** documenter une réponse, c'est aussi documenter explicitement *l'absence* de contenu.

## 6.10 Erreurs à éviter quand on écrit des attributs OA

| Erreur | Pourquoi c'est un problème |
|---|---|
| Déclarer `OA\Post` sans `Route` | La documentation ne crée pas l'endpoint Symfony |
| Déclarer un `requestBody` JSON sans traitement JSON correspondant | Swagger permet une saisie que le serveur ne sait peut-être pas traiter |
| Documenter `200` sur un POST qui retourne `201` | Le client risque d'interpréter incorrectement la réussite |
| Documenter une erreur `422` alors que le contrôleur ne l'implémente pas | Le contrat promet une garantie absente |
| Décrire un objet alors que le code renvoie une liste | Le frontend risque de mal interpréter la réponse |
| Mettre un `content` dans `204` | Le contrat prétend qu'une réponse sans corps transporte des données |
| Considérer `required` OpenAPI comme un Validator | Le backend n'exécute pas les schémas OA automatiquement |
| Utiliser `example: 1` comme si cet ID existait toujours | Les exemples ne reflètent pas nécessairement l'état courant de la BDD |

### Bilan du niveau TagOA

Il faut désormais savoir expliquer **chaque argument OA important**, et surtout distinguer :

```text
Symfony Route     -> « comment cette requête est exécutée »
OpenAPI           -> « quel comportement le client peut attendre »
Swagger UI        -> « comment le contrat est affiché et testé »
Symfony Validator -> « quelles contraintes sont réellement évaluées » (étape suivante)
```

La logique de TagOA reste volontairement presque identique à Basic. **L'ajout d'OpenAPI seul ne rend pas une API sûre ni valide.** C'est précisément le problème du niveau suivant.

---
# 7. Niveau 3 — VERIFIED : contrôler les données

**Code à ouvrir :** `ProductController.php`, bloc `NIVEAU 3 — VERIFIED`, ainsi que `src/Dto/Product/*` et `src/Service/ProductWriter.php`.  
**Groupe Swagger :** `Products / 3 — Verified`.  
**Base des URL :** `/api/learning/products/verified`.

## 7.1 Le problème que nous voulons résoudre

Dans Basic et TagOA, notre POST lit directement le JSON et l'envoie aux setters :

```php
$data = $request->toArray();
$product->setStock($data['stock']);
```

Cette simplicité masque des questions :

- Que se passe-t-il si `stock` manque ?
- Que se passe-t-il si `stock` vaut `-5` ?
- Que se passe-t-il si le client envoie `"stock":"beaucoup"` ?
- Le client peut-il envoyer un champ imprévu, comme `id` ?
- Comment garder les mêmes règles de validation pour plusieurs endpoints ?
- Pourquoi exposer directement les propriétés d'une entité Doctrine pourrait-il devenir dangereux ?

Verified introduit les objets et composants qui apportent des réponses à ces problèmes. **Nous allons volontairement voir leur fonctionnement dans des opérations CRUD différentes**, avant de les assembler dans Final.

## 7.2 Vocabulaire essentiel : Entity, DTO, Serializer, Validator

### Entity : le modèle de persistance

`Product` contient les données mappées vers les colonnes de la base. Doctrine sait le charger, observer ses modifications et le persister. **Son objectif n'est pas de définir le contrat HTTP.**

### DTO : Data Transfer Object

Un DTO est un objet conçu pour transporter un ensemble explicite de données **à une frontière de l'application**. Un `CreateProductInput` décrit ce qu'un client a le droit de fournir lors d'une création ; un `ProductOutput` décrit ce que le serveur décide de renvoyer.

Un DTO ne persiste rien, n'envoie pas automatiquement de réponse HTTP, et n'applique pas lui-même de contraintes. C'est un support de données dont la structure peut comporter des annotations de validation.

### Serializer : convertir les représentations

Le Serializer possède notamment deux directions :

```text
DÉSÉRIALISATION : JSON reçu -> objet PHP (CreateProductInput)
SÉRIALISATION   : objet PHP  -> données JSON envoyées
```

Il ne faut pas confondre **désérialisation** et **validation métier**. Le Serializer peut détecter une impossibilité de conversion, mais il ne sait pas spontanément qu'un stock négatif est interdit : c'est le Validator qui applique cette règle.

### Validator : exécuter les contraintes

Le Validator inspecte un objet et ses attributs `#[Assert\...]`. Il renvoie une collection de *violations* ; aucune modification SQL n'est effectuée par cette opération.

On l'appelle explicitement avec :

```php
$violations = $validator->validate($input);
```

ou on laisse Symfony le déclencher automatiquement dans un mécanisme comme `MapRequestPayload`.

### Service : exécuter l'action applicative

`ProductWriter` reçoit des DTO **déjà contrôlés** et applique les modifications sur les entités Doctrine. Le contrôleur gère HTTP ; le service gère l'écriture. Le service ne devrait pas connaître les statuts `201`, `404` ni les attributs `OA`.

## 7.3 GET collection : découvrir le Serializer et les groupes

**À lire :** `verifiedIndex()`.

```php
$products = $repository->findBy([], ['id' => 'ASC']);
return $this->json($products, 200, [], ['groups' => ['product:read']]);
```

Dans Basic, nous construisions explicitement chaque tableau `['name' => $product->getName(), ...]`. Ici, nous fournissons directement au mécanisme de réponse un tableau **d'entités Product**, plus un contexte de sérialisation.

### Comprendre les quatre arguments de `$this->json()`

```php
$this->json($data, $status, $headers, $context)
```

| Position | Dans ce code | Rôle |
|---|---|---|
| 1 | `$products` | Objets ou données à transformer en JSON |
| 2 | `200` | Statut HTTP |
| 3 | `[]` | En-têtes supplémentaires, aucun ici |
| 4 | `['groups' => ['product:read']]` | Contexte envoyé au Serializer |

Les attributs `#[Groups(['product:read'])]` présents sur les propriétés de `Product.php` déterminent celles qui sont exportées.

**Test guidé :** GET `/verified`. Attendre `200`, une liste dont les champs publics ressemblent à ceux du GET Basic. Comparer les **deux bodies réels** ; la différence importante est le **chemin de construction**, pas nécessairement le JSON final.

**Ce qu'il faut en déduire :** les groupes simplifient la sérialisation et permettent une sélection de champs. Ils ont toutefois une contrepartie : le contrat du client dépend de métadonnées attachées à l'entité Doctrine. Une API complexe peut préférer un DTO de sortie dédié.

## 7.4 GET unitaire : découvrir un DTO de sortie

**À lire :** `verifiedShow()` puis `ProductOutput.php`.

```php
return $this->json(ProductOutput::fromEntity($product));
```

`ProductOutput::fromEntity()` est une **méthode statique de fabrique** : elle reçoit un `Product` et construit un nouvel objet `ProductOutput` contenant uniquement les champs que l'API publie.

```text
Product (entité Doctrine)
       |
       | ProductOutput::fromEntity($product)
       v
ProductOutput (contrat de sortie)
       |
       | $this->json(...)
       v
Réponse HTTP JSON
```

### Décomposer `public static function fromEntity(...)`

- `public` : cette méthode peut être appelée depuis une autre classe, ici le contrôleur.
- `static` : on l'appelle sur **la classe** avec `ProductOutput::fromEntity(...)`, sans créer d'abord un objet `ProductOutput`.
- `Product $product` : le paramètre attendu est une entité `Product`.
- `: self` : le résultat doit être une instance de la classe `ProductOutput`.
- `new self(...)` : construit l'objet retourné à partir des getters de l'entité.

La classe `ProductOutput` est également `readonly` : ses propriétés sont initialisées lors de sa construction et ne peuvent plus être modifiées arbitrairement par la suite. Cela convient bien à une représentation de sortie immuable.

**Test guidé :** GET `/verified/{id}` avec un ID existant. Comparer avec GET `/basic/{id}` : les valeurs doivent représenter **le même produit**, mais le chemin passe maintenant par une classe de transfert explicite.

**Pourquoi créer une classe supplémentaire alors que le JSON est similaire ?** Parce que l'entité pourra évoluer sans exposer automatiquement ses nouveaux champs. Le frontend dépend de la forme publique de `ProductOutput`, pas de toutes les propriétés internes de `Product`.

## 7.5 POST : DTO d'entrée + Serializer explicite

**À lire :** `verifiedCreate()` et `CreateProductInput.php`.

Extrait du contrôleur :

```php
$input = $serializer->deserialize(
    $request->getContent(),
    CreateProductInput::class,
    'json',
);
```

Décomposer les arguments :

1. `$request->getContent()` : **chaîne brute** du body de la requête.
2. `CreateProductInput::class` : nom de la **classe PHP cible** ; le Serializer sait quel type d'objet construire.
3. `'json'` : indique le format du texte source.
4. `$input` : objet DTO résultant. C'est désormais une structure avec des propriétés comme `$input->name` et `$input->stock`, et non un tableau `$data['stock']`.

### Pourquoi `CreateProductInput::class` et pas `new CreateProductInput()` ?

`::class` est une construction PHP qui produit le nom complet d'une classe, utilisable comme information de type. Le Serializer utilise ce nom pour construire l'objet. Le contrôleur ne fournit pas une instance existante à remplir manuellement.

### Pourquoi des valeurs par défaut dans le DTO ?

Exemple de `CreateProductInput` :

```php
public string $name = '';
public ?string $price = null;
public ?int $stock = null;
public bool $isActive = true;
```

Elles évitent de laisser des propriétés typées non initialisées lorsqu'un champ manque. Mais elles ne rendent **pas** automatiquement le DTO valide : `name = ''`, `price = null` et `stock = null` doivent être rejetés par les contraintes appropriées.

`isActive = true`, en revanche, correspond à une **valeur par défaut souhaitée**. Le client peut omettre ce champ pendant la création.

### Pourquoi refuser `id` dans un DTO d'entrée ?

L'ID appartient au serveur : le client ne choisit pas sa clé primaire. `CreateProductInput` ne possède donc pas de propriété `id`. Le contrôleur Verified refuse en plus les clés inconnues avant la désérialisation ; la version finale applique cette liste blanche à toutes les opérations d'écriture.

C'est un exemple de prévention du **mass assignment** : on ne transforme pas aveuglément chaque clé fournie par le client en modification de propriétés internes.

## 7.6 POST : lancer le Validator et lire ses violations

Après la désérialisation, `verifiedCreate()` appelle :

```php
$violations = $validator->validate($input);
if (count($violations) > 0) {
    // Construire une réponse 422 avec field + message.
}
```

Il faut comprendre que `$violations` est une **collection de problèmes**, pas un booléen `true/false`. Chaque violation possède notamment :

- `getPropertyPath()` : nom de la propriété du DTO concernée ;
- `getMessage()` : message associé à la contrainte non respectée.

Par exemple, un nom trop court et un stock négatif peuvent produire **plusieurs erreurs dans une même réponse**. C'est plus utile qu'un simple `"Erreur"` sans précision.

### Décrypter les contraintes du DTO

Ouvrir `CreateProductInput.php` :

| Contrainte | Ce qu'elle vérifie | Exemple d'échec |
|---|---|---|
| `#[Assert\NotBlank]` | La valeur ne doit pas être vide au sens de cette contrainte | `name: ""` |
| `#[Assert\Length(min: 2, max: 150)]` | Longueur autorisée d'une chaîne | `name: "A"` |
| `#[Assert\Regex(pattern: ...)]` | La valeur doit correspondre à une expression régulière | `price: "12.345"` |
| `#[Assert\NotNull]` | Une valeur `null` n'est pas acceptable | `stock` absent (valeur par défaut `null`) |
| `#[Assert\PositiveOrZero]` | Nombre supérieur ou égal à zéro | `stock: -1` |

Chaque `Assert` porte sur une propriété PHP du DTO. Le Validator ne modifie pas la valeur pour la rendre correcte : il **signale** un écart.

### L'expression régulière du prix

Le DTO utilise :

```php
pattern: '/^\d{1,8}(?:\.\d{1,2})?$/'
```

Elle se lit ainsi :

| Élément | Signification |
|---|---|
| `/.../` | Délimiteurs de l'expression régulière en PHP |
| `^` | Début de la chaîne |
| `\d{1,8}` | Entre 1 et 8 chiffres pour la partie entière |
| `(?:...)` | Groupe sans capture, utilisé pour organiser une partie de l'expression |
| `\.` | Point **littéral** : séparateur décimal |
| `\d{1,2}` | 1 ou 2 chiffres après le point |
| `?` après le groupe | Partie décimale facultative |
| `$` | Fin de la chaîne |

Exemples acceptés par **cette** règle : `"0"`, `"0.00"`, `"12"`, `"12.5"`, `"12.50"`. Exemples refusés : `"-1"`, `"12.345"`, `"12,50"`, `"abc"`. Ce n'est pas un parseur monétaire universel : c'est la règle choisie pour le contrat de cet atelier.

### Exercice Verified POST A — création valide

Dans Swagger, ouvrir POST `/verified`, puis envoyer :

```json
{
  "name": "Hub USB-C Verified",
  "description": "Six ports",
  "price": "39.90",
  "stock": 4
}
```

**Résultat attendu :** `201` et **un `ProductOutput` complet**, notamment `"isActive": true`. C'est intéressant : contrairement au POST Basic, la réponse n'est pas seulement `id` + `message`. Le DTO de sortie décrit la représentation publique entière.

**Pourquoi `isActive` est-il vrai ?** Le JSON n'avait pas cette clé, donc la valeur par défaut du DTO de création a été conservée.

### Exercice Verified POST B — contraintes invalides

Envoyer :

```json
{
  "name": "A",
  "price": "12.345",
  "stock": -2
}
```

**Résultat attendu :** `422`, `code: "VALIDATION_FAILED"`, un tableau `details` contenant les propriétés qui posent problème. Aucun produit ne doit être ajouté.

**Pourquoi 422 et pas 400 ?** Le body est un JSON lisible et les données ont les types de base attendus. Les **règles de valeur** échouent : longueur du nom, format du prix, signe du stock.

### Exercice Verified POST C — mauvais type

Envoyer :

```json
{"name":"Produit test","price":"12.90","stock":"beaucoup"}
```

**Résultat attendu :** `400` avec `INVALID_TYPES` dans cette méthode. Le Serializer n'arrive pas à convertir le stock textuel en un entier exploitable. Cette erreur intervient **avant** que la validation des contraintes ne puisse accepter le DTO.

### Exercice Verified POST D — propriété non autorisée

Envoyer :

```json
{"id":999,"name":"Produit test","price":"12.90","stock":2}
```

**Résultat attendu :** `422`, `UNKNOWN_FIELDS`, avec la liste des noms de champs refusés.

**Ce que cela prouve :** les données d'entrée sont contrôlées **avant** la création de l'entité et aucune valeur d'ID fournie par le client n'est utilisée.

## 7.7 PUT : comprendre `#[MapRequestPayload]`

**À lire :** `verifiedReplace()`.

```php
public function verifiedReplace(
    int $id,
    #[MapRequestPayload(acceptFormat: 'json')] ReplaceProductInput $input,
    ProductRepository $repository,
    ProductWriter $writer,
): JsonResponse
```

Cet attribut demande à Symfony de traiter automatiquement une partie des opérations réalisées manuellement dans `verifiedCreate()` :

```text
Corps de requête
       |
       v
Résolution de l'argument MapRequestPayload
       |
       +-- décodage du format d'entrée
       |
       +-- construction du ReplaceProductInput
       |
       +-- évaluation des contraintes Assert
       |
       v
Exécution de verifiedReplace() avec un DTO préparé
```

**La différence principale :** le paramètre `$input` est prêt **au moment où le corps de la méthode s'exécute**. L'attribut est traité dans la préparation des arguments du contrôleur par Symfony.

### Décomposer `#[MapRequestPayload(acceptFormat: 'json')]`

- `MapRequestPayload` : mécanisme Symfony de transformation automatique d'un body vers un argument typé.
- `acceptFormat: 'json'` : limite les formats de payload acceptés au JSON.
- `ReplaceProductInput $input` : type cible attendu et nom local de la variable.
- Les contraintes `Assert` sont récupérées dans `ReplaceProductInput.php`.
- Une violation de contrainte produit **par défaut `422`** ; d'autres problèmes de payload peuvent produire `400` ou `415` selon leur nature.

### Pourquoi la réponse d'erreur est-elle différente ?

Pour un POST Verified, **notre code** récupère les violations et construit `{'code':'VALIDATION_FAILED', ...}`. Pour ce PUT, **Symfony** gère les erreurs avant le code du contrôleur. Le format retourné est donc celui de son gestionnaire d'erreurs, et peut différer de notre petit objet JSON personnalisé.

La classe de contrôleur déclare `format: 'json'` sur sa route : cela indique à Symfony que les réponses d'erreur attendues pour ces routes doivent être représentées comme du JSON.

### Exercice Verified PUT A — remplacement complet

Utiliser l'ID du produit créé dans **Verified POST A**, puis envoyer :

```json
{
  "name": "Hub USB-C amélioré",
  "description": "Huit ports",
  "price": "44.90",
  "stock": 9,
  "isActive": false
}
```

**Résultat attendu :** `200` et un `ProductOutput` reprenant le nouvel état. Refaire GET `/verified/{id}` : les modifications doivent être présentes.

### Exercice Verified PUT B — champ obligatoire manquant

Envoyer le même JSON **sans `isActive`**. `ReplaceProductInput` utilise `public ?bool $isActive = null;` et possède `#[Assert\NotNull]`. Cela signifie que le champ absent laissera la propriété à `null`, puis le Validator constatera une violation.

**Résultat attendu :** `422` généré par Symfony. Observer **la forme de l'erreur**, pas seulement son code. Comparer avec la réponse du POST Verified B.

### Exercice Verified PUT C — règle sur le nom

Envoyer un état complet avec `"name":""`, les autres propriétés étant correctes. Attendre `422`. Cette fois, c'est `Assert\NotBlank` qui rejette le DTO.

**Important :** `MapRequestPayload` n'est pas une solution universelle à toutes les règles d'une API. Il ne remplace ni la décision des champs autorisés, ni les règles métier spécifiques, ni une stratégie d'erreur homogène. Le contrôleur Final montrera l'alternative choisie dans cet atelier.

## 7.8 PATCH : le vrai piège, « absent » contre `null`

**À lire :** `PatchProductInput.php`, `verifiedPatch()` puis `ProductWriter::patch()`.

Supposer un produit :

```json
{"name":"Hub USB-C","description":"Six ports","price":"39.90","stock":4,"isActive":true}
```

Comparer ces quatre bodies :

| Body PATCH | Effet voulu |
|---|---|
| `{"stock":2}` | Mettre le stock à 2, ne rien changer d'autre |
| `{"description":null}` | Effacer la description |
| `{"name":null}` | **Refuser** : le nom d'un produit ne doit pas devenir `null` |
| `{}` | **Refuser** : aucune modification demandée |

Un DTO partiel possède des propriétés initialisées à `null` :

```php
public ?string $name = null;
public ?string $description = null;
public ?string $price = null;
public ?int $stock = null;
public ?bool $isActive = null;
```

Après désérialisation, si `description === null`, **cette seule valeur ne dit pas** si le client a envoyé `"description":null` ou s'il a omis la propriété. Il faut conserver en parallèle les **clés réellement présentes** dans le JSON.

```php
$data = $this->verifiedJsonObject($request);
// ... désérialisation et validation ...
$writer->patch($product, $input, array_keys($data));
```

`array_keys($data)` fournit les noms des propriétés explicitement envoyées. Le Writer n'exécute un setter que si le nom correspondant est présent dans cette liste.

### Pourquoi `Assert\NotBlank(allowNull: true)` dans le DTO PATCH ?

Pour `name`, la propriété `null` représente parfois **l'absence de modification**. Si le Validator rejetait systématiquement `null` sur cette propriété, un PATCH ne contenant que `stock` deviendrait invalide : `name` serait absent, donc `null` dans le DTO.

`allowNull: true` empêche donc le Validator de considérer le `null` *par défaut* comme une violation de `NotBlank`. **Cela ne veut pas dire qu'un client a le droit d'envoyer explicitement `"name":null`**. Le contrôleur vérifie ce second cas grâce aux clés du body.

On combine deux contrôles complémentaires :

1. **Présence et null explicite** : traitement des clés fournies (`array_key_exists`).
2. **Validité de la valeur non nulle** : contraintes du DTO (`Assert`).

### Exercice Verified PATCH A — absence de champ

PATCH `/verified/{id}` :

```json
{"stock":2}
```

Attendre `200`. Refaire GET et vérifier que `name`, `price`, `description` et `isActive` n'ont pas bougé.

### Exercice Verified PATCH B — null autorisé

PATCH sur le même ID :

```json
{"description":null}
```

Attendre `200`. GET doit montrer `"description":null`. Le Writer a vu que `description` figurait dans `array_keys($data)`.

### Exercice Verified PATCH C — null interdit

```json
{"name":null}
```

Attendre `422`, `NULL_NOT_ALLOWED`. Le Writer **ne doit pas être appelé** avec un nom `null`.

### Exercice Verified PATCH D — valeur non valide

```json
{"stock":-4}
```

Attendre `422`, `VALIDATION_FAILED` : le type est correct (`integer`) mais la valeur échoue à `Assert\PositiveOrZero`.

### Exercice Verified PATCH E — body vide

```json
{}
```

Attendre `422`, `EMPTY_PATCH`. Le contrôleur a une règle explicite : la modification partielle doit demander au moins un changement.

**À retenir :** PATCH est le cas où connaître un DTO et lancer un Validator ne suffit pas toujours. Il faut aussi comprendre les **opérations de modification** et la différence entre « non fourni », `null` et « valeur fournie ».

## 7.9 DELETE : faire intervenir un service

**À lire :** `verifiedDelete()` et `ProductWriter::delete()`.

Dans Basic :

```php
$em->remove($product);
$em->flush();
```

Dans Verified :

```php
$writer->delete($product);
```

Le service contient maintenant les appels Doctrine. Dans la méthode du contrôleur, il reste l'identification de la ressource, la décision de répondre `404` si elle manque, puis la construction du `204` après succès.

**Test :** supprimer le produit d'essai Verified **en dernier**, après tous les PATCH. Attendre `204`, puis `404` au prochain GET.

**Le bénéfice n'est pas de gagner deux lignes.** C'est de maintenir une frontière : le service ne connaît pas HTTP, le contrôleur ne décide pas des instructions d'écriture SQL. Cette séparation devient plus précieuse lorsque les règles métier sont plus complexes.

## 7.10 Le petit précontrôle `verifiedJsonObject()`

POST et PATCH Verified utilisent cette méthode privée pour vérifier deux conditions communes :

1. Le `Content-Type` de la requête correspond au format JSON attendu ; sinon `415`.
2. Le body est un **objet JSON valide** (`{...}`), pas une liste `[...]`, un entier ou un texte invalide ; sinon `400`.

Le résultat de cette méthode est soit un tableau associatif PHP, soit une `JsonResponse` d'erreur.

```php
$data = $this->verifiedJsonObject($request);
if ($data instanceof JsonResponse) {
    return $data;
}
```

`instanceof` teste ici si le résultat est une réponse HTTP d'erreur plutôt qu'un tableau à exploiter. Ce style sera repris de façon plus systématique par `readInput()` dans Final.

### Bilan du niveau Verified

Le chemin complet d'une création contrôlée peut maintenant se lire ainsi :

```text
POST + JSON
   |
   v
Contrôles de format et de champs
   |
   v
Serializer -> CreateProductInput
   |
   v
Validator -> violations ?
   |                   |
   | oui               | non
   v                   v
422 avec détails       ProductWriter -> Doctrine
                               |
                               v
                        ProductOutput -> JSON 201
```

Il faut savoir différencier **DTO, Serializer et Validator**, expliquer le raccourci `MapRequestPayload`, et justifier pourquoi PATCH a besoin de connaître **les clés présentes**. Le contrôleur Final va maintenant utiliser ces notions aux bons endroits et les associer à un contrat OpenAPI complet.

---
# 8. Niveau 4 — FINAL : réunir les techniques

**Code à ouvrir :** `ProductFinalController.php`, puis les cinq DTO et `ProductWriter.php`.  
**Groupe Swagger :** `Products / 4 — Final`.  
**Base des URL :** `/api/final/products`.

## 8.1 Ce qui change dans la version finale

Les versions précédentes servaient à **découvrir** les techniques en les isolant. Final cherche au contraire à présenter une **organisation cohérente** :

```text
Client
   |
   v
Route + Contrôleur final (HTTP)
   |
   +---- GET ------> ProductRepository -----------------+
   |                                                     |
   +---- POST/PUT/PATCH --> readInput()                   |
   |                           |                         |
   |                    JSON valide ?                    |
   |                    clés et types ?                  |
   |                           |                         |
   |                        Serializer                   |
   |                           |                         |
   |                          DTO                        |
   |                           |                         |
   |                        Validator                    |
   |                           |                         |
   |                       ProductWriter                  |
   |                           |                         |
   |                        Doctrine <-------------------+
   |                           |
   +----------- ProductOutput::fromEntity() <-------------+
                               |
                               v
                    Réponse HTTP + JSON
```

**Les responsabilités :**

- Le contrôleur identifie l'opération et traduit son résultat en statut HTTP.
- Le repository lit les produits.
- Le DTO d'entrée décrit les champs admis.
- `readInput()` contrôle l'entrée et coordonne Serializer + Validator.
- `ProductWriter` applique la création, le remplacement, la modification ou la suppression.
- `ProductOutput` choisit les champs publics de la réponse.
- Les attributs OpenAPI décrivent ce comportement à Swagger.

Cette organisation n'impose pas que chaque projet possède exactement les mêmes méthodes. C'est **un choix explicite**, adapté au CRUD de cet exercice.

## 8.2 Pourquoi ne pas utiliser `MapRequestPayload` partout ?

Verified PUT montre une solution Symfony concise, utile et recommandable. Dans Final, `readInput()` a été conservée pour **maîtriser localement trois décisions** :

1. Rejeter de manière stricte les champs inconnus et les mauvais types JSON, sans dépendre d'éventuelles conversions automatiques.
2. Utiliser la **même forme d'erreur** pour POST, PUT et PATCH.
3. Gérer explicitement les règles de présence propres à PATCH.

Ce choix ajoute du code au contrôleur. C'est un compromis à comprendre, pas un reproche contre `MapRequestPayload` : dans une API réelle, on pourrait également adopter `MapRequestPayload` et un gestionnaire central d'erreurs et de validation. Pour cet atelier, un helper privé est plus simple à étudier qu'une architecture d'exceptions globale.

## 8.3 `readInput()` : comprendre chaque vérification et son ordre

**À lire :** méthode privée `readInput()` en bas de `ProductFinalController.php`.

Elle renvoie soit un DTO validé, soit une `JsonResponse` indiquant le problème. Les étapes s'enchaînent dans un ordre défini :

| Ordre | Contrôle | Exemple de rejet | Statut |
|---|---|---|---|
| 1 | Format déclaré `Content-Type` | `text/plain` | `415` |
| 2 | JSON valide | `{"name":` | `400` |
| 3 | Racine objet JSON | `[1,2,3]` | `400` |
| 4 | Liste blanche des champs | `"id":999` | `422` |
| 5 | Champs obligatoires | POST sans `stock` | `422` |
| 6 | Cas PATCH particulier | `{}` ou `{"name":null}` | `422` |
| 7 | Types JSON exacts | `"stock":"5"` ou `"price":12.5` | `400` |
| 8 | Désérialisation | Valeurs impossibles à convertir vers le DTO | `400` |
| 9 | Validation des contraintes | `"stock":-1` | `422` |
| 10 | Écriture | Uniquement si les étapes précédentes réussissent | Succès |

### Pourquoi valider les types JSON séparément ?

Certains mécanismes de conversion peuvent accepter ou transformer des valeurs compatibles. Pour un contrat pédagogique strict, on veut distinguer :

```json
{"stock":5}
```

et :

```json
{"stock":"5"}
```

Le premier est un **nombre entier JSON** ; le second est une **chaîne JSON** contenant le caractère `5`. Dans Final, seul le premier est accepté pour `stock`. En revanche, un prix doit être une chaîne : `"price":"12.50"` est acceptable, `"price":12.5` ne l'est pas.

Le contrôle PHP utilise `is_string()`, `is_int()` et `is_bool()` avant de passer au Serializer. Il évite de laisser la conversion implicite décider du contrat d'entrée.

### À quoi sert `match ($type)` ?

Dans `readInput()` :

```php
$required = match ($type) {
    CreateProductInput::class => ['name', 'price', 'stock'],
    ReplaceProductInput::class => ['name', 'price', 'stock', 'isActive'],
    PatchProductInput::class => [],
};
```

`match` est une expression PHP qui retourne une valeur selon l'option retenue. Ici, le **type de DTO demandé** détermine les propriétés qui doivent figurer dans le JSON.

- En POST, `isActive` est facultatif parce que sa valeur par défaut est `true`.
- En PUT, `isActive` doit être fourni, parce qu'on définit tout l'état modifiable.
- En PATCH, aucun **champ en particulier** n'est requis, mais le JSON ne doit pas être vide.

Ces exigences sont des règles du **contrat d'entrée**. Elles ne découlent pas uniquement du typage des propriétés PHP.

### Pourquoi une union de types en retour ?

```php
private function readInput(...): CreateProductInput|ReplaceProductInput|PatchProductInput|JsonResponse
```

Le symbole `|` signifie que la fonction peut retourner **l'un de ces types**. Ce n'est pas une liste d'objets : un seul de ces types est effectivement retourné à chaque appel.

Dans l'action POST :

```php
$input = $this->readInput(...);
if ($input instanceof JsonResponse) {
    return $input;
}
assert($input instanceof CreateProductInput);
```

- Si l'entrée est invalide, `readInput` renvoie directement une erreur HTTP : le contrôleur n'écrit rien en base.
- Sinon, on sait que l'objet doit être le DTO de création.
- `assert()` exprime ici une garantie de type pour le lecteur et l'analyse statique ; **ce n'est pas la validation de données clientes**. La validation a eu lieu précédemment.

## 8.4 `ApiErrorOutput` : un contrat d'erreur stable

Une erreur de la version finale ressemble à :

```json
{
  "code": "VALIDATION_FAILED",
  "message": "Les données ne respectent pas les contraintes.",
  "details": [
    {
      "field": "stock",
      "message": "Le stock ne peut pas être négatif."
    }
  ]
}
```

Chaque partie a un usage différent :

- `code` : identifiant stable que le frontend peut exploiter pour distinguer les cas d'erreur ;
- `message` : phrase explicative destinée à un humain ;
- `details` : liste des problèmes associés aux champs ; elle peut être vide pour une erreur globale.

`ApiErrorOutput.php` déclare désormais explicitement la structure de `details` dans le **PHPDoc** :

```php
/** @var list<array{field: string, message: string}> */
public array $details;
```

Cette notation **n'est pas un type PHP natif**. PHP voit une propriété `array`, alors que PHPStan peut comprendre qu'il s'agit d'une **liste de tableaux possédant exactement les clés `field` et `message`, toutes deux de type string**.

Le même format apparaît dans la documentation OpenAPI grâce à `OA\Property(type:'array', items: new OA\Items(...))`. Le premier décrit le type **pour l'analyse PHP**, le second décrit le type **pour le client de l'API**. Ce sont deux responsabilités différentes.

**Limite à connaître :** la cohérence du format est garantie par les réponses que **ce contrôleur construit explicitement**. Une exception imprévue générée ailleurs dans Symfony peut encore emprunter un autre format. Nous n'ajoutons pas ici de gestionnaire global qui modifierait le comportement du reste de Meditech Care.

## 8.5 GET collection et GET unitaire : choisir la sortie publique

Final utilise systématiquement :

```php
ProductOutput::fromEntity($product)
```

Pour une collection, `array_map()` applique cette transformation à tous les produits :

```php
$products = $repository->findBy([], ['id' => 'ASC']);
return $this->json(array_map(ProductOutput::fromEntity(...), $products));
```

La syntaxe `ProductOutput::fromEntity(...)` utilise ici la possibilité PHP de produire un callable à partir d'une méthode, ensuite passé à `array_map`. Le résultat est un **tableau de DTO ProductOutput**.

**Test GET collection :** ouvrir GET `/api/final/products`. Attendre `200` et une liste de produits complets. Comparer les champs avec ceux du GET Verified.

**Test GET unitaire :** choisir un ID existant. Attendre `200` et un seul objet. Recommencer avec `999999999` : attendre `404` et `ApiErrorOutput` avec `PRODUCT_NOT_FOUND`.

## 8.6 POST Final : DTO validé, création et en-tête Location

**À lire :** `create()`, `readInput()` et `ProductWriter::create()`.

```php
$product = $writer->create($input);
$location = $this->generateUrl('api_final_products_show', ['id' => $product->getId()]);
return $this->json(ProductOutput::fromEntity($product), 201, ['Location' => $location]);
```

**Nouveauté :** la réponse `201` comporte un en-tête **`Location`** qui indique au client où consulter la ressource créée. `generateUrl()` utilise **le nom de route Symfony** et les paramètres donnés, au lieu de concaténer une URL à la main.

Une réponse peut donc contenir **trois choses indépendantes** : un statut `201`, un en-tête `Location` et un body JSON représentant le produit.

### Test POST Final A — réussite

Envoyer :

```json
{
  "name": "Support écran Final",
  "description": "Support réglable",
  "price": "32.50",
  "stock": 9,
  "isActive": true
}
```

**Attendre :**

- `201` comme statut HTTP ;
- un body contenant un `ProductOutput` complet, notamment un ID réel ;
- un en-tête `Location` semblable à `/api/final/products/42` (avec l'ID réellement créé).

**Vérification :** copier l'ID et appeler GET `/api/final/products/{id}`. Le body doit retrouver les valeurs envoyées. **Pourquoi ?** Le `Location` annonce une ressource persistée et consultable, pas seulement un message de succès.

### Test POST Final B — nom vide

```json
{"name":"","price":"32.50","stock":9}
```

Attendre `422`, `VALIDATION_FAILED`, avec un détail sur `name`. Le JSON est correct : c'est la valeur qui viole `NotBlank`.

### Test POST Final C — prix de mauvais type

```json
{"name":"Support écran","price":32.5,"stock":9}
```

Attendre `400`, `INVALID_TYPES`, avec un détail sur `price`. Cette fois, le problème est **le type JSON**, pas seulement le format du prix.

### Test POST Final D — champ inconnu

```json
{"name":"Support écran","price":"32.50","stock":9,"id":999}
```

Attendre `422`, `UNKNOWN_FIELDS`. Cela démontre que l'ID est entièrement contrôlé par le serveur.

## 8.7 PUT Final : remplacer entièrement les propriétés modifiables

**À lire :** `replace()` et `ProductWriter::replace()`.

L'ID est extrait de la route ; le contrôleur vérifie que le produit existe ; `readInput()` construit un `ReplaceProductInput` validé ; le Writer modifie l'entité et déclenche `flush()`.

### Test PUT Final A — remplacement valide

Sur l'ID créé en 8.6, envoyer :

```json
{
  "name": "Support écran Final 2",
  "description": null,
  "price": "35.00",
  "stock": 4,
  "isActive": false
}
```

Attendre `200`. Le body est un **ProductOutput mis à jour** ; contrôler que `description` vaut `null`, `stock` vaut `4` et `isActive` vaut `false`.

### Test PUT Final B — état incomplet

Enlever `isActive` du JSON. Attendre `422`, `MISSING_FIELDS` ; le serveur exige un état complet pour les propriétés non-nullables. La base ne doit pas avoir été modifiée.

**Test complémentaire :** enlever uniquement `description`, mais garder les quatre autres champs. Ce PUT est accepté ; la description est fixée à `null` par la valeur par défaut du DTO. Cela correspond au contrat documentaire affiché par Swagger.

## 8.8 PATCH Final : présence, types, contraintes, persistance

**À lire :** `patch()`, `readInput()` et `ProductWriter::patch()`.

Le DTO décrit les valeurs ; le tableau de noms de propriétés décrit **ce qui doit être modifié**. Pour le contrôle des types, `readInput()` s'assure également qu'une chaîne n'est pas silencieusement acceptée comme entier.

### Test PATCH Final A — un seul champ

```json
{"stock":1}
```

Attendre `200` et vérifier que les autres champs n'ont pas changé.

### Test PATCH Final B — effacement volontaire

```json
{"description":null}
```

Attendre `200`, puis voir `description: null` dans le body.

### Test PATCH Final C — null impossible

```json
{"name":null}
```

Attendre `422`, `NULL_NOT_ALLOWED`, avec un détail ciblant `name`.

### Test PATCH Final D — propriété inconnue

```json
{"quantite":3}
```

Attendre `422`, `UNKNOWN_FIELDS`, puisque le vrai nom du champ est `stock`. La version finale n'ignore pas silencieusement les propriétés inconnues.

### Test PATCH Final E — entier dans une chaîne

```json
{"stock":"3"}
```

Attendre `400`, `INVALID_TYPES` : la chaîne `"3"` n'est pas un entier JSON, même si sa lecture semble numérique.

**Pourquoi cet ensemble de contrôles ?** Un PATCH doit modifier précisément les données voulues **et rien d'autre**. Il faut connaître les champs fournis, rejeter ceux qui ne font pas partie du contrat, contrôler les types et les valeurs, puis appliquer uniquement les setters concernés.

## 8.9 DELETE Final : aucune représentation dans le body

**À lire :** `delete()` et `ProductWriter::delete()`.

Supprimer **le produit créé pour ce chapitre**, après avoir terminé tous les tests dessus. Attendre `204`, puis `404` lorsque son ID est demandé par GET.

Côté OpenAPI, l'annotation de succès est volontairement courte :

```php
new OA\Response(response: 204, description: 'Supprimé. Body strictement absent.')
```

Elle **ne contient pas** `content: new OA\JsonContent(...)`. C'est précisément ce qui rend le contrat cohérent avec le `204` réellement retourné.

## 8.10 La documentation OpenAPI finale, par rapport à TagOA

Dans TagOA, les schémas `LearningProductCreate` et `LearningProductReplace` sont décrits **manuellement par des attributs sur le contrôleur**. Dans Final, les DTO portent eux-mêmes leurs attributs de description :

```php
#[OA\Schema(/* ... */)]
final class CreateProductInput
{
    #[OA\Property(type: 'string', example: 'Clavier mécanique')]
    public string $name = '';
}
```

Et le contrôleur y fait référence grâce à Nelmio :

```php
new OA\JsonContent(ref: new Model(type: CreateProductInput::class))
```

`new Model(...)` demande à Nelmio de construire un modèle de documentation à partir d'une **classe PHP**. Cela évite de maintenir à la main un deuxième schéma complètement séparé pour chaque DTO.

**Attention :** les contraintes Symfony (`Assert`) et les métadonnées OpenAPI (`OA`) poursuivent deux objectifs distincts. Les conserver côte à côte facilite la maintenance, mais il faut vérifier que les réponses réellement observées correspondent à ce qui est décrit.

### Décrypter `OA\Header` sur le POST

```php
headers: [
    new OA\Header(
        header: 'Location',
        description: 'Chemin du GET unitaire de la ressource créée',
        schema: new OA\Schema(type: 'string'),
    ),
],
```

C'est la **documentation** de l'en-tête `Location`. Ce bloc ne crée pas l'en-tête ; c'est le troisième argument de `$this->json(..., 201, ['Location' => $location])` qui l'ajoute effectivement dans la réponse HTTP.

### Bilan de la version finale

Un contrôleur API correctement structuré doit permettre de répondre à ces questions :

1. Quel est son **contrat d'entrée** (champs, types, valeurs) ?
2. Qui **applique réellement** les contraintes ?
3. Quelle est la frontière entre HTTP et persistance ?
4. Quels champs le client reçoit-il **en sortie** ?
5. Quels statuts et formats d'erreur sont prévisibles ?
6. Est-ce que la documentation Swagger correspond à ce que le code renvoie ?

L'objectif n'est pas de réciter les attributs ni de multiplier les classes : c'est de savoir **choisir et justifier la responsabilité de chaque composant**.

---
# 9. Parcours de tests complets dans Swagger

Ce chapitre sert de **fiche de travaux pratiques** : il peut être suivi après la lecture du cours, ou utilisé plus tard comme protocole de vérification lorsque le code évolue.

## 9.1 Test de référence : créer, lire, modifier, supprimer

Pour **chaque niveau**, suivre le même scénario avec un produit neuf :

| Ordre | Opération | Action dans Swagger | Ce que vérifier |
|---|---|---|---|
| 1 | GET collection | Exécuter sans paramètre | `200` et liste JSON |
| 2 | POST | Créer un produit valide, noter son ID | `201`, nouvel ID |
| 3 | GET `/{id}` | Consulter l'ID créé | `200`, mêmes données |
| 4 | PUT `/{id}` | Remplacer les données | `200`, état entièrement mis à jour |
| 5 | PATCH `/{id}` | Modifier seulement le stock | `200`, autres propriétés inchangées |
| 6 | PATCH `/{id}` | Envoyer `{"description":null}` | `200`, description effacée |
| 7 | DELETE `/{id}` | Supprimer le produit d'essai | `204`, body absent |
| 8 | GET `/{id}` | Relire le même ID | `404`, ressource absente |

**Rappel :** l'ID retourné par POST peut être différent pour chaque niveau. Il ne faut pas réutiliser l'ID d'un produit déjà supprimé.

## 9.2 Comparatif des quatre POST

Le moyen le plus direct de comprendre les différences est de comparer **quatre créations valides** :

| Niveau | Comment lire l'entrée | Comment valider | Comment écrire | Réponse de succès |
|---|---|---|---|---|
| Basic | `Request::toArray()` | Pas de validation applicative | `EntityManager` dans le contrôleur | `201`, `id` + `message` |
| TagOA | `Request::toArray()` | Pas de validation applicative | `EntityManager` dans le contrôleur | `201`, `id` + `message` |
| Verified | `Serializer::deserialize()` vers DTO | `Validator::validate()` | `ProductWriter` | `201`, `ProductOutput` |
| Final | `readInput()` puis Serializer vers DTO | Contrôles des types et clés + Validator | `ProductWriter` | `201`, `ProductOutput` + `Location` |

**Interprétation centrale :** les quatre POST créent un `Product` en base. Ce sont le contrôle des entrées, la séparation des responsabilités, la sortie et la documentation qui changent.

## 9.3 Comparatif des réponses GET

| Niveau | Méthode de construction du JSON | Contrat de sortie |
|---|---|---|
| Basic | Tableau PHP écrit manuellement | Défini implicitement par les clés du tableau |
| TagOA | Tableau PHP écrit manuellement | Même JSON, documenté explicitement via OpenAPI |
| Verified collection | Serializer + `Groups` | Défini par les groupes sur l'entité |
| Verified unitaire | `ProductOutput::fromEntity()` | DTO de sortie |
| Final | DTO `ProductOutput` sur toutes les lectures | Contrat unique et explicite |

**Exercice de lecture :** ouvrir les cinq méthodes correspondantes dans les contrôleurs et retrouver le code exact qui décide du nom `isActive` ou du type de `price` dans le résultat.

## 9.4 Tests d'erreur à réaliser sur Final

Ces tests sont conçus pour ne **pas** créer ni modifier d'enregistrement lorsqu'une entrée est invalide.

| Test | Body ou situation | Résultat attendu |
|---|---|---|
| Nom absent | POST `{"price":"10.00","stock":1}` | `422 MISSING_FIELDS` |
| Nom vide | POST `{"name":"","price":"10.00","stock":1}` | `422 VALIDATION_FAILED` |
| Nom trop court | POST avec `"name":"A"` | `422 VALIDATION_FAILED` |
| Stock négatif | POST avec `"stock":-1` | `422 VALIDATION_FAILED` |
| Stock en texte | POST avec `"stock":"un"` | `400 INVALID_TYPES` |
| Stock en chaîne numérique | POST avec `"stock":"2"` | `400 INVALID_TYPES` |
| Prix avec trois décimales | POST avec `"price":"12.345"` | `422 VALIDATION_FAILED` |
| Prix numérique JSON | POST avec `"price":12.50` | `400 INVALID_TYPES` |
| Propriété d'ID injectée | POST avec `"id":999` en plus | `422 UNKNOWN_FIELDS` |
| PUT incomplet | PUT sans `isActive` sur un ID existant | `422 MISSING_FIELDS` |
| PATCH vide | PATCH `{}` sur un ID existant | `422 EMPTY_PATCH` |
| PATCH null interdit | PATCH `{"name":null}` | `422 NULL_NOT_ALLOWED` |
| PATCH description effacée | PATCH `{"description":null}` | `200`, `description:null` |
| PATCH propriété inconnue | PATCH `{"quantite":2}` | `422 UNKNOWN_FIELDS` |
| GET ID inexistant | GET `/999999999` | `404 PRODUCT_NOT_FOUND` |
| Suppression réitérée | DELETE sur un ID déjà supprimé | `404 PRODUCT_NOT_FOUND` |

**Pour tester un JSON malformé** (`{"name":` par exemple), utiliser Swagger seulement s'il permet de transmettre tel quel ce texte : selon son éditeur, il peut signaler la syntaxe avant même l'envoi. Les tests de contenu volontairement invalide peuvent être réalisés avec un client HTTP séparé. Les parcours principaux restent entièrement faisables depuis Swagger.

**Pour tester `415`**, il faut envoyer un `Content-Type` autre que JSON. Swagger choisit généralement automatiquement `application/json` conformément au contrat ; ce cas spécifique nécessite donc parfois un client HTTP permettant de modifier les en-têtes.

## 9.5 Comment savoir si une erreur est correcte ?

Après chaque échec, répondre à ces cinq questions :

1. **Quelle opération** était demandée ? Par exemple POST, et non PUT.
2. **Le body a-t-il été lu comme JSON ?** Sinon, le problème se situe avant la validation métier.
3. **Est-ce un problème de type ou de règle de valeur ?** `"stock":"abc"` n'a pas le bon type ; `"stock":-3` a un type entier correct, mais une valeur interdite.
4. **Le serveur a-t-il écrit en base ?** Une réponse d'échec sur une création doit laisser la base inchangée.
5. **La réponse correspond-elle au contrat Swagger ?** Le statut, les champs et les données d'erreur observés doivent être cohérents.

Un `500` sur Basic avec des données incorrectes illustre l'absence de garde-fous. Le même test sur Final doit conduire à un **rejet contrôlé** pour les cas prévus par le contrat. Si un `500` apparaît sur Final pour un des scénarios documentés, il faut diagnostiquer le problème plutôt que le considérer comme normal.

## 9.6 Tableau de résultats à compléter pendant l'atelier

| Test | Résultat Basic | Résultat TagOA | Résultat Verified | Résultat Final |
|---|---|---|---|---|
| GET collection valide | | | | |
| GET ID existant | | | | |
| GET ID absent | | | | |
| POST valide | | | | |
| POST nom vide | *Ne pas tester volontairement* | *Ne pas tester volontairement* | | |
| POST stock négatif | *Ne pas tester volontairement* | *Ne pas tester volontairement* | | |
| PUT complet | | | | |
| PATCH stock uniquement | | | | |
| PATCH `description:null` | | | | |
| DELETE valide | | | | |

**Pourquoi ne pas commencer par des entrées invalides dans Basic/TagOA ?** Ces niveaux ne les contrôlent pas et peuvent persister des valeurs incohérentes, lever une exception ou provoquer une erreur en base. C'est la comparaison avec Verified et Final qui rend ces garde-fous intéressants, sans qu'il faille volontairement corrompre le jeu de données.

---

# 10. Comprendre chaque fichier et chaque responsabilité

## 10.1 Carte des fichiers

| Fichier | Responsabilité | Où son fonctionnement apparaît |
|---|---|---|
| `ProductController.php` | Trois CRUD comparables : Basic, TagOA, Verified | Chapitres 5, 6, 7 |
| `ProductFinalController.php` | CRUD de référence, HTTP et coordination | Chapitre 8 |
| `CreateProductInput.php` | Champs et contraintes de POST | §7.5–7.6, §8.6 |
| `ReplaceProductInput.php` | Champs et contraintes de PUT | §7.7, §8.7 |
| `PatchProductInput.php` | Champs facultatifs de PATCH | §7.8, §8.8 |
| `ProductOutput.php` | Représentation publique d'un produit | §7.4, §8.5 |
| `ApiErrorOutput.php` | Format des erreurs construites par Final | §8.4 |
| `ProductWriter.php` | `create`, `replace`, `patch`, `delete` avec Doctrine | §7.9, §8.1 |
| `Product.php` *(inchangé)* | Entité Doctrine, getters/setters et groupes | §4.1 |
| `ProductRepository.php` *(inchangé)* | Lecture des produits via Doctrine | §4.2 |
| `ProductFixtures.php` *(inchangé)* | Produits de test reproductibles | §2.3 |

## 10.2 Pourquoi trois DTO d'entrée différents ?

On pourrait être tenté d'utiliser **un seul DTO pour POST, PUT et PATCH**. Mais ces opérations n'ont pas les mêmes règles :

- POST ne demande pas d'ID et peut attribuer des valeurs par défaut.
- PUT exige l'état complet des champs modifiables, pour remplacer la ressource.
- PATCH n'exige pas les mêmes champs, mais doit connaître **ceux qui ont réellement été fournis**.

Séparer `CreateProductInput`, `ReplaceProductInput` et `PatchProductInput` rend ces différences explicites. Cela évite de mélanger des règles contradictoires au sein d'une seule classe, même si certains champs sont répétés.

## 10.3 Pourquoi `ProductOutput` est distinct de `CreateProductInput` ?

Le client ne doit pas envoyer d'ID pour créer un produit, mais la réponse à la création doit pouvoir contenir cet ID. La sortie peut aussi contenir des données calculées ou attribuées par le serveur.

Les responsabilités sont donc asymétriques :

```text
CreateProductInput                 ProductOutput
  name (entrée)                      id (sortie, généré)
  price (entrée)                     name (sortie)
  stock (entrée)                     price (sortie)
  description (entrée)               stock (sortie)
  isActive (entrée)                  description (sortie)
                                    isActive (sortie)
```

Le nom `Input` ou `Output` est une **convention de lecture** utile. Ce sont toujours de simples classes PHP ; le framework ne les valide pas uniquement à cause de leur nom.

## 10.4 Pourquoi `ProductWriter` protège ses méthodes ?

Dans le service, certains DTO possèdent des propriétés typées `?string`, `?int` ou `?bool` pour permettre la détection des champs manquants. Mais les setters de l'entité exigent des valeurs définies pour `price`, `stock` et `isActive`.

Avant d'appeler les setters, le Writer contient des vérifications de cohérence indiquant que **les données auraient dû être validées en amont**. Une violation de cet invariant entraîne une `LogicException` : elle signale une erreur de programmation interne, et non une erreur utilisateur normale.

Le traitement normal est : **entrée reçue → DTO validé → Writer**. Ce n'est pas le Writer qui doit essayer de « réparer » une requête invalide.

## 10.5 Le bon ordre de lecture d'une nouvelle API Symfony

Quand tu liras un futur contrôleur Meditech Care, tu pourras appliquer ce parcours :

1. Lire le **besoin métier** et identifier la ressource manipulée.
2. Lire `#[Route]` pour comprendre le verbe, l'URL et les variables de chemin.
3. Lire les attributs `OA` pour comprendre le **contrat annoncé**.
4. Lire la **signature** de la méthode pour identifier les dépendances et les DTO attendus.
5. Lire le traitement de l'entrée et les **contrôles**.
6. Suivre les appels au repository ou au service, puis à l'entité.
7. Vérifier la construction de la **réponse** et son code HTTP.
8. Exécuter au moins un scénario nominal et un scénario d'erreur dans Swagger.
9. Comparer le résultat observable au contrat initial.

Ce chemin permet de comprendre un contrôleur sans s'arrêter à une simple explication ligne par ligne.

---

# 11. Erreurs fréquentes et diagnostic

## 11.1 « La route ne s'affiche pas dans Swagger »

**À vérifier dans cet ordre :**

1. `php bin/console debug:router` : la route existe-t-elle dans Symfony ?
2. La classe possède-t-elle les bons attributs `Route` et `OA` ?
3. La configuration `nelmio_api_doc.yaml` couvre-t-elle le préfixe `/api/` ?
4. Une erreur de syntaxe PHP empêche-t-elle l'analyse du contrôleur ?
5. L'interface est-elle actualisée après modification des fichiers ?

**Distinction clé :** une route peut exister et répondre correctement, mais ne pas être décrite comme prévu dans Swagger si sa documentation OpenAPI n'est pas reconnue.

## 11.2 « Swagger n'affiche pas le JSON à envoyer »

La possibilité de saisir un body dépend du `requestBody` **documenté**. C'est la raison pour laquelle Basic possède déjà des attributs OA minimaux sur POST, PUT et PATCH. Un endpoint peut accepter un body en PHP sans que Swagger sache quels champs proposer si son contrat n'est pas renseigné.

Pour le niveau TagOA, vérifier également que la référence `#/components/schemas/LearningProductCreate` (ou `Replace`, `Patch`) est bien résolue par le document OpenAPI.

## 11.3 « Mon GET retourne 404 »

Vérifier la différence entre ces cas :

- URL absente de la table des routes : **404 de routing** ;
- `id` non numérique ne correspondant pas au `requirements` : **404 de routing** ;
- `id` numérique mais inconnu de Doctrine : **404 de l'action** ;
- produit supprimé quelques instants auparavant : **404 de l'action**, état normal après la suppression.

Commencer par un GET collection pour relever un ID **actuellement existant**.

## 11.4 « Le POST fonctionne mais je ne vois pas mon produit »

Vérifier successivement le **code HTTP**, la **base de données ciblée** par l'environnement et l'ID retourné. Un POST à succès écrit en base ; si le GET interroge un autre environnement ou un autre serveur, il ne voit pas nécessairement le même jeu de données.

Ne pas utiliser un ID supposé fixe. L'auto-incrémentation avance avec les insertions et peut conserver des trous après des suppressions.

## 11.5 « Le POST Basic produit un 500 avec une entrée incomplète »

C'est précisément la **limite volontaire** du premier niveau : les setters reçoivent directement les valeurs du tableau de requête. Un champ manquant ou un type impossible peut provoquer une exception. Utiliser un body valide pour étudier Basic ; étudier le rejet contrôlé sur Verified et Final.

Si un **body valide** produit un `500`, ce n'est pas attendu. Consulter `var/log/dev.log`, l'exception Symfony et le schéma Doctrine.

## 11.6 « L'erreur du PUT Verified ne ressemble pas à l'erreur du POST Verified »

C'est un comportement à observer : le POST construit une réponse personnalisée ; le PUT utilise `MapRequestPayload`, qui produit des erreurs via Symfony. Dans Final, la méthode `readInput()` et `ApiErrorOutput` donnent une forme commune aux erreurs **explicitement traitées**.

Le statut et le **format du body** sont deux notions distinctes. Deux erreurs `422` peuvent avoir des structures JSON différentes si le backend n'a pas adopté une convention partagée.

## 11.7 « Mon PATCH efface une valeur que je voulais conserver »

Vérifier si le code applique les setters à **toutes** les propriétés du DTO ou seulement aux **clés présentes dans le JSON**. Le DTO de PATCH utilise `null` par défaut ; il ne sait pas, tout seul, si le client a volontairement envoyé `null`.

Relire les tests `{"stock":2}` et `{"description":null}` : ils doivent avoir des effets différents. Vérifier aussi que `name:null` et `stock:null` sont rejetés.

## 11.8 « PHPStan signale un tableau sans type de valeurs »

Dans PHP, le type `array` n'explique pas la structure interne. PHPStan peut demander des informations supplémentaires, par exemple :

```php
/** @var list<array{field: string, message: string}> */
public array $details;
```

Il faut aussi documenter le paramètre correspondant au constructeur avec `@param`. Cette notation décrit une liste de tableaux à deux clés `string`. Ce n'est pas une règle OpenAPI : c'est une précision apportée à l'analyse statique du code PHP.

## 11.9 « L'entité change mais pas le JSON »

Avec des groupes, les propriétés non rattachées à `product:read` ne sont pas forcément publiées. Avec `ProductOutput`, seules les propriétés explicitement construites dans `fromEntity()` sortent dans la réponse. C'est **un comportement souhaité** : le contrat public ne doit pas évoluer accidentellement quand le modèle de BDD change.

## 11.10 « Je veux réinitialiser les produits d'essai »

Utiliser de préférence une base de développement isolée et prendre garde aux autres tables Meditech Care. `doctrine:fixtures:load` purge par défaut ; `--append` évite cette purge mais ajoute des doublons lors de chaque nouvelle exécution. Ne jamais lancer une commande de purge générale sans avoir vérifié la connexion et la portée de la suppression.

---

# 12. Synthèse, vocabulaire et suite du cours

## 12.1 Glossaire rapide

| Terme | Définition utile |
|---|---|
| **Endpoint** | Opération accessible par combinaison méthode HTTP + chemin |
| **Route** | Règle Symfony associant une requête à une action PHP |
| **Controller** | Code coordonnant l'entrée HTTP, les services et la réponse HTTP |
| **Request** | Objet Symfony décrivant la requête entrante |
| **Response** | Objet Symfony décrivant le statut, les en-têtes et le body sortants |
| **JSON** | Format textuel de représentation des données, indépendant de PHP |
| **Body** | Corps de la requête ou de la réponse HTTP |
| **Path parameter** | Valeur faisant partie du chemin, par exemple `/{id}` |
| **Query parameter** | Valeur placée après `?` dans une URL, par exemple `?active=1` |
| **Content-Type** | En-tête indiquant le format du contenu envoyé |
| **Status code** | Code HTTP représentant le résultat d'une opération |
| **Entity** | Objet Doctrine correspondant à des données persistées |
| **Repository** | Composant chargé de retrouver des entités |
| **EntityManager** | Composant Doctrine suivant et enregistrant les modifications |
| **DTO Input** | Objet décrivant les données que l'API reçoit |
| **DTO Output** | Objet décrivant les données que l'API publie |
| **Serializer** | Composant convertissant des objets et représentations JSON |
| **Validator** | Composant évaluant les contraintes `Assert` sur des objets |
| **Violation** | Résultat d'une contrainte de validation non respectée |
| **MapRequestPayload** | Attribut Symfony préparant un argument DTO à partir du body et le validant |
| **OpenAPI** | Standard décrivant le contrat d'une API HTTP |
| **Swagger UI** | Interface affichant un contrat OpenAPI et permettant d'essayer les requêtes |
| **NelmioApiDocBundle** | Bundle Symfony générant la documentation OpenAPI à partir du code et de ses métadonnées |
| **Schema** | Description structurée d'un objet ou d'une donnée dans le contrat OpenAPI |
| **`$ref`** | Référence vers un composant de contrat défini ailleurs dans le document OpenAPI |
| **`Assert`** | Contrainte Symfony appliquée par le Validator |
| **Groups** | Métadonnées sélectionnant des propriétés pendant la sérialisation |
| **Idempotence** | Répéter la même requête a le même effet final sur l'état de la ressource |
| **Mass assignment** | Risque de modifier des propriétés internes en recopiant aveuglément le contenu du client |

## 12.2 Les quatre niveaux en une seule lecture

```text
BASIC
  "Je sais faire fonctionner un CRUD HTTP avec Doctrine."
       |
       v
TAGOA
  "Je sais décrire précisément ce CRUD pour son client."
       |
       v
VERIFIED
  "Je sais transformer et contrôler les données d'entrée et de sortie."
       |
       v
FINAL
  "Je sais assembler ces composants de façon cohérente et justifiable."
```

## 12.3 Auto-évaluation

À la fin, être capable de répondre **sans regarder le guide**, puis de retrouver la preuve dans le code :

1. Pourquoi `requirements: ['id' => '\d+']` ne vérifie-t-il pas l'existence du produit ?
2. Pourquoi faut-il un `requestBody` OpenAPI pour décrire un POST testable correctement dans Swagger ?
3. Que signifie `required` sur `OA\RequestBody`, par rapport à `required` sur `OA\Schema` ?
4. Pourquoi un exemple OpenAPI n'est-il pas une règle de validation ?
5. Pourquoi `GET` collection et `GET` unitaire ne renvoient-ils pas la même structure racine ?
6. Quelles différences existe-t-il entre `Product`, `CreateProductInput` et `ProductOutput` ?
7. Que fait exactement `SerializerInterface::deserialize()` ?
8. À quel moment `#[Assert\PositiveOrZero]` est-il effectivement évalué ?
9. Comment `MapRequestPayload` intervient-il **avant** le corps de la méthode ?
10. Pourquoi la description peut-elle être remise à `null` en PATCH mais pas le nom ?
11. Comment la liste `array_keys($data)` empêche-t-elle de modifier des propriétés absentes ?
12. Pourquoi `ProductWriter` ne doit-il pas retourner de `JsonResponse` ?
13. Pourquoi la version finale refuse-t-elle `"stock":"3"` alors qu'il ressemble à un nombre ?
14. À quoi sert l'en-tête `Location` après la création ?
15. Pourquoi une réponse `204` ne doit-elle pas contenir de JSON ?

Si une réponse est hésitante, refaire **un seul test ciblé** au niveau qui a introduit la notion, plutôt que relire tout le code du début à la fin.

## 12.4 Correspondance avec les cinq supports du cours

| Support fourni | Notions réinvesties ici |
|---|---|
| **API : concepts et fonctionnement** | Verbes, routes, HTTP, statuts, body, idempotence, erreurs, séparation des responsabilités |
| **Construire une API Symfony from scratch** | Controllers, Doctrine, DTO, Serializer, Validator, service, réponses et erreurs |
| **OpenAPI : décrire et contractualiser une API** | Contrats, `paths`, paramètres, `requestBody`, `responses`, schémas, `$ref`, exemples |
| **Swagger : exploiter une description OpenAPI** | Lecture du contrat, `Try it out`, erreurs, comparaison entre document et réponse réelle |
| **Consommer une API tierce** | Point de vue du client qui dépend d'un contrat stable ; cet atelier fournit l'API plutôt que de consommer un fournisseur externe |

## 12.5 Documentation officielle à garder comme référence

Les liens suivants complètent les explications de l'atelier et permettent de vérifier les détails de version :

- [Symfony — Controller et MapRequestPayload](https://symfony.com/doc/current/controller.html)
- [Symfony — Serializer](https://symfony.com/doc/current/serializer.html)
- [Symfony — Validation](https://symfony.com/doc/current/validation.html)
- [Symfony — Routing](https://symfony.com/doc/current/routing.html)
- [NelmioApiDocBundle](https://symfony.com/bundles/NelmioApiDocBundle/current/index.html)
- [swagger-php — référence des attributs classiques](https://zircote.com/swagger-php/reference/attributes.html)
- [Spécification OpenAPI](https://spec.openapis.org/oas/latest.html)
- [DoctrineFixturesBundle — chargement de fixtures](https://symfony.com/doc/current/DoctrineFixturesBundle/index.html)

## 12.6 Périmètre et prudence

Cet atelier ne définit volontairement **aucune authentification ni autorisation** pour les routes d'écriture. Elles sont donc destinées à un environnement **local ou isolé**, pas à un serveur public exposé. La sécurité d'une vraie API Meditech Care devra contrôler les droits de l'utilisateur **côté serveur**, indépendamment de ce que le frontend affiche.

Les exercices utilisent **des tests manuels via Swagger**. Ils permettent de constater le comportement actuel, mais ne remplacent pas une suite automatisée de tests de régression. Les tests fonctionnels Symfony pourront être ajoutés dans le cadre du projet principal, une fois les contrats et les règles de chaque ressource définis.

**Objectif final de l'atelier :** ne plus considérer un endpoint comme « une méthode qui renvoie du JSON », mais comme **un contrat observable** reposant sur des responsabilités clairement séparées, des données contrôlées et une documentation fidèle à son comportement.
