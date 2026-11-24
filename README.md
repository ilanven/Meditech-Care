# Meditech Care

**Meditech Care** est un projet de développement d'application de gestion pour le secteur du matériel médical, réalisé dans le cadre du **BTS SIO, option SLAM**.

L'objectif est de construire une application permettant de gérer notamment les employés, les techniciens, le matériel et les interventions. Le projet est **en cours de développement** : toutes les fonctionnalités métier ne sont pas encore disponibles.

## Comment fonctionne le projet ?

L'application repose sur deux parties distinctes :

- **Backend (ce dépôt)** : une API développée en **PHP 8.4 / Symfony 8.1**, qui reçoit des requêtes HTTP, applique les règles métier et échange avec la base de données via **Doctrine**.
- **Frontend (prévu séparément)** : une interface utilisateur en **Svelte**, qui communiquera avec l'API pour afficher et modifier les données.

L'API échange principalement des données au format **JSON**. Sa documentation interactive est accessible avec **Swagger UI**, à partir d'une description **OpenAPI**.

## 1. Prérequis

Installer sur sa machine :

- **Git**, pour récupérer le code ;
- **PHP 8.4 ou plus récent**, avec les extensions requises par le projet ;
- **Composer**, pour installer les dépendances PHP ;
- **MySQL**, ou un autre service de BDD (et adapter `DATABASE_URL` du .env) ;
- **Symfony CLI** (recommandé), pour lancer facilement le serveur local.

## 2. Installer le projet

### Récupérer le code

```bash
git clone https://github.com/ilanven/Meditech-Care.git
cd Meditech-Care
```

### Installer les dépendances

```bash
composer install
```

### Configurer la base de données

Dupliquer le fichier **`.env.example`** et le renommer **`.env.local`** à la racine du projet, pour définir les paramètres propres à sa machine sans modifier le `.env` partagé.

Exemple avec MySQL :

```dotenv
DATABASE_URL="postgresql://utilisateur:mot_de_passe@127.0.0.1:5432/meditech_care?serverVersion=16&charset=utf8"
```

Adapter l'utilisateur, le mot de passe et la version du serveur MySQL. S'assurer que MySQL est démarré, puis créer la base et appliquer les migrations :

```bash
php bin/console doctrine:database:create
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

> Si la base existe déjà, ne pas relancer sa création. Ne pas commiter `.env.local` : il contient des informations locales, potentiellement sensibles.

## 3. Lancer et découvrir l'API

Démarrer le serveur à la racine du projet :

```bash
symfony server:start
```

Ouvrir ensuite **[Swagger UI](http://127.0.0.1:8000/api/doc)**, si le serveur fonctionne sur le port `8000`.

Swagger permet de **consulter les routes de l'API et d'envoyer des requêtes directement depuis le navigateur** :

1. Ouvrir une opération (`GET`, `POST`, `PATCH`, etc.).
2. Cliquer sur **Try it out**.
3. Renseigner les paramètres ou le JSON demandé, puis cliquer sur **Execute**.
4. Lire le **code HTTP** et la **réponse JSON** renvoyés par le serveur.

**Attention :** les appels effectués dans Swagger sont réels. Un `POST` peut créer des données, et un `DELETE` peut les supprimer.

### Jeu de données pour l'atelier Product

Pour ajouter dix produits de démonstration sans vider les tables existantes :

```bash
php bin/console doctrine:fixtures:load --group=ProductFixtures --append
```

Cette commande ajoute à nouveau les produits à chaque exécution. Utiliser une base de développement pour les essais.

## 4. Se repérer dans le code

| Emplacement         | Rôle                                                                             |
| ------------------- | -------------------------------------------------------------------------------- |
| `src/Controller/`   | Reçoit les requêtes HTTP et renvoie les réponses.                                |
| `src/Entity/`       | Définit les objets enregistrés en base de données.                               |
| `src/Repository/`   | Recherche et récupère les données avec Doctrine.                                 |
| `src/Dto/`          | Définit les données échangées à l'entrée et à la sortie de certaines opérations. |
| `src/Service/`      | Regroupe certaines opérations et règles applicatives.                            |
| `src/DataFixtures/` | Prépare des données de démonstration.                                            |
| `migrations/`       | Contient les évolutions de la structure de la base de données.                   |
| `config/`           | Contient la configuration de Symfony et de ses composants.                       |
| `doc/`              | Contient les explications et guides détaillés du projet.                         |

**Parcours simplifié d'une requête :**

`Client → route Symfony → contrôleur → service / repository → base de données → réponse JSON`

Selon l'opération, des DTO, le Serializer et le Validator peuvent aussi intervenir.

## 5. Comprendre l'atelier API Product

Cette branche contient un **atelier pédagogique** autour de l'entité `Product`. Il permet de découvrir progressivement la construction d'une API Symfony en comparant quatre versions d'un même CRUD :

| Niveau       | URL de base                       | Objectif                                          |
| ------------ | --------------------------------- | ------------------------------------------------- |
| **Basic**    | `/api/learning/products/basic`    | Comprendre les routes, le JSON et Doctrine.       |
| **TagOA**    | `/api/learning/products/tagOA`    | Ajouter la documentation OpenAPI.                 |
| **Verified** | `/api/learning/products/verified` | Valider les données avec des DTO et le Validator. |
| **Final**    | `/api/final/products`             | Réunir ces notions dans une version de référence. |

Ces quatre versions utilisent **la même table `product`**. Elles sont destinées à l'apprentissage et ne représentent pas les fonctionnalités métier finales de Meditech Care.

Le tutoriel complet, avec les explications et les exercices Swagger, se trouve dans **[doc/ATELIER_API_PRODUCT.md](doc/ATELIER_API_PRODUCT.md)**.

## 6. Participer au développement

La branche **`main`** est destinée aux versions stables ; **`develop`** sert de base au développement commun. Les fonctionnalités et corrections sont travaillées sur des branches dédiées, puis proposées par **pull request** vers `develop`.

Avant de commencer une modification, consulter les documents pertinents dans **[`doc/`](doc/)**, notamment les spécifications et les consignes de travail disponibles. Cela évite de dupliquer ici les explications détaillées.

Quelques commandes utiles :

```bash
php bin/console debug:router               # Voir les routes disponibles
php bin/console doctrine:migrations:status # Vérifier l'état des migrations
composer qa                            # Lancer l'analyse statique (si configurée)
```

---

**Projet pédagogique — BTS SIO SLAM.** Le code et les fonctionnalités évoluent au fil du développement.
