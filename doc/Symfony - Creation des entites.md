# Symfony — Création des entités et relations (Meditech Care)

> **But de cette page :** partir de notre MCD Merise pour créer les entités Symfony, leurs propriétés et leurs relations Doctrine, puis mettre à jour la base de données. Toutes les commandes sont à lancer **depuis la racine du projet**, dans un terminal.

> **Référence :** onglet **MCD** du fichier `Merise MeditechCare.drawio`. Pour l'instant, seul ce MCD est à jour : ne pas considérer les autres diagrammes comme validés. Pour le visualiser, aller sur Draw.io et y importer le fichier doc/Merise MeditechCare.drawio. L'onglet 'MCD' est le premier en bas.

## 0. Comprendre ce qu'on va créer

| Élément | Rôle |
| --- | --- |
| **Entité (`src/Entity/`)** | Classe PHP qui représente les données d'un objet métier. Doctrine la relie à une table SQL. |
| **Propriété** | Information de l'entité (`nom`, `email`, `dateIntervention`…). Ca corepond à une colonne SQL, sauf les collections de relations. |
| **Relation** | Lien entre deux entités (`ManyToOne`, `OneToMany`…). Doctrine gère les clés (Foreign key et Primary key en fonction de ces relations). |
| **Repository (`src/Repository/`)** | Classe utilisée pour rechercher et récupérer des données de l'entité. Le repository possède les requêtes préparées pour la base de données. |
| **Migration (`migrations/`)** | Fichier qui décrit les modifications SQL à effectuer pour mettre la BDD à jour. |

**Notre méthode :** lire le MCD → créer les entités sans relations → ajouter les relations selon les cardinalités → vérifier → générer et exécuter la migration.

**Trois repères :**

- Une **entité** du MCD devient généralement une classe Symfony ; choisir un nom de classe au **singulier** (`Client`, `Appareil`, `Employe`…).

- Un **attribut** du MCD devient généralement une propriété (`nomEtablissement`, `numeroSerie`…). Le champ `id` est créé automatiquement par `make:entity` : inutile d'ajouter `idClient` ou `idAppareil` à la main, sauf besoin particulier.

- Une **association** du MCD devient une relation Doctrine. **Ne pas créer soi-même une propriété `fkClient` de type entier** : utiliser une propriété `client` de type `relation`.

## 1. Préparer le travail

**Avant de commencer :** Vérifier la configuration de la BDD (`DATABASE_URL` dans `.env.local` ou dans la configuration du projet).

**Si besoin de configurer le .env.local :**

- Dupliquer le fichier `.env` et le renommer `.env.local` (ou créer `.env.local` s'il n'existe pas). Conserver le fichier `.env` d'origine.
- Ouvrir `.env.local` et renseigner `DATABASE_URL` avec les informations du serveur MySQL local :
```dotenv
DATABASE_URL="mysql://utilisateur:mot_de_passe@127.0.0.1:3306/meditech_care?serverVersion=8.0.46&charset=utf8mb4"
```
Remplacer `utilisateur`, `mot_de_passe` et `serverVersion` par les valeurs de sa machine (WAMP et PHPMyAdmin). `meditech_care` correspond au nom de la BDD à créer. Si le mot de passe contient des caractères spéciaux, les encoder dans l'URL. (en local on va souvent utiliser root et pas de mdp).

**Créer la BDD si elle n'existe pas :**
```bash
php bin/console doctrine:database:create
```
Vérifier que la BDD apparaît dans phpMyAdmin. **Ne pas commiter `.env.local`** : il peut contenir des identifiants propres à la machine.

> **Attention :** `make:entity` crée ou modifie des fichiers PHP, **pas les tables SQL**. La BDD sera mise à jour plus loin, avec les migrations.

## 2. Lire le MCD avant de créer une entité

Pour chaque entité du MCD, relever :

1. Son **nom** : choisir le nom PHP au singulier.

2. Ses **attributs** : noter leur nom, le type attendu et si la valeur peut être absente.

3. Ses **associations** : relever les deux entités concernées et leurs cardinalités.

**Les entités présentes dans notre MCD :**

| MCD | Nom de classe associée |
| --- | --- |
| `Clients` | `Client` |
| `Appareils` | `Appareil` |
| `Employes` | `Employe` |
| `Intervention` | `Intervention` |
| `MaterielIntervention` | `MaterielIntervention` |
| `MaterielStock` | `MaterielStock` |
| `Indisponibilites` | `Indisponibilite` |
| `CrenauxTravail` | `CreneauTravail` |

> Les noms de classes ci-dessus sont une **convention Symfony proposée**. Garder des noms cohérents partout dans le code.

## 3. Créer une entité et ses propriétés

**Exemple — créer `Appareil` :**
```bash
php bin/console make:entity Appareil
```
Symfony propose d'ajouter des propriétés une par une. D'après le MCD, saisir par exemple :

| Nom de la propriété | Type Doctrine proposé | Précision |
| --- | --- | --- |
| `marque` | `string` | 255 caractères maximum par défaut |
| `modele` | `string` | 255 caractères maximum par défaut |
| `annee` | `integer` | Année numérique, si l'information est bien un nombre |
| `numeroSerie` | `string` | Un numéro de série peut contenir des lettres et des zéros initiaux |

**Pour chaque propriété :** entrer son nom → choisir le type → renseigner la longueur si demandée → répondre à la question `nullable`.

- **`nullable: no`** : valeur obligatoire en BDD.

- **`nullable: yes`** : valeur pouvant être absente (`NULL`).

**Attention :** le MCD ne précise pas systématiquement quels attributs sont obligatoires. Décider ces règles métier avant de répondre ; ne pas choisir `nullable` au hasard. Même principe pour les longueurs, les valeurs uniques et les types des dates.

Appuyer sur **Entrée sans saisir de nom de propriété** pour terminer.

**Fichiers obtenus :**
```text
src/Entity/Appareil.php

src/Repository/AppareilRepository.php
```
Répéter pour les autres entités. **Créer d'abord les propriétés simples ; ajouter les relations à l'étape suivante.**

### Quelques types utiles

| Type Doctrine | Utilisation |
| --- | --- |
| `string` | Nom, email, téléphone, SIREN, numéro de série, statut simple… |
| `text` | Description ou diagnostic long |
| `integer` | Quantité, année numérique, durée entière… |
| `decimal` | Valeur décimale exacte (retournée généralement sous forme de chaîne en PHP) |
| `boolean` | Oui/non |
| `date_immutable` | Date sans heure |
| `datetime_immutable` | Date et heure |
| `relation` | Lien avec une autre entité : **à utiliser pour les associations du MCD** |

Pour `debut`, `fin`, `dateDiagnostic` ou `dateIntervention`, choisir `date_immutable` ou `datetime_immutable` selon le besoin d'enregistrer **une heure**. Pour les durées, fixer une unité commune (par exemple, la minute).

### Cas particulier : créer une entité utilisateur (`Employe`)

Pour une entité qui doit se connecter avec l’authentification Symfony, **utiliser `make:user` à la place de `make:entity` pour sa création** :

```bash
php bin/console make:user
```

Choisir `Employe` comme nom de classe, **yes** pour le stockage Doctrine, `email` comme identifiant et **yes** pour le hashage des mots de passe. Symfony crée l'entité compatible avec son système de sécurité. **Ne pas ajouter de propriété `mdp`** : utiliser le champ `password` généré (mot de passe hashé).

Ajouter ensuite les autres attributs du MCD comme pour une entité classique :

```bash
php bin/console make:entity Employe
```

### Cas particulier : utiliser un enum (`poste`, `type`…)

Un **enum** limite une propriété à une liste de valeurs définies, par exemple les postes possibles d'un employé.

**Créer d'abord l'enum** dans `src/Enum/Poste.php` :

```php
<?php

namespace App\Enum;

enum Poste: string
{
    case Technicien = 'technicien';
    case Responsable = 'responsable';
}
```

**Ajouter ensuite la propriété** avec `make:entity Employe` : nom `poste` → type `enum` → classe `App\Enum\Poste` → une seule valeur → choisir `nullable` selon le besoin. Doctrine stocke la valeur sous forme de chaîne en BDD, mais PHP manipule un `Poste`.

Pour éviter les confusions de noms, préférer `TypeIndisponibilite` pour l'enum de l'entité `Indisponibilite`. Les valeurs ci-dessus sont des exemples à adapter au MCD et aux règles métier.

## 4. Comprendre les relations et les cardinalités

**La question à se poser :** « Pour **un objet A**, combien d'objets B peuvent être liés ? » Lire les cardinalités **de chaque côté** du MCD.

| Cardinalités MCD | Traduction Doctrine habituelle |
| --- | --- |
| `0,N` ↔ `1,1` | `ManyToOne` côté `1,1` ; `OneToMany` possible côté `0,N` |
| `0,N` ↔ `0,1` | `ManyToOne` **nullable** côté `0,1` ; `OneToMany` possible côté `0,N` |
| `0,N` ↔ `0,N` | `ManyToMany` si la relation n'a pas d'attribut propre |
| `1,1` ↔ `0,1` (ou `1,1`) | `OneToOne` selon la règle métier |

**Important :** la relation `ManyToOne` suffit à enregistrer une clé étrangère. Ajouter le `OneToMany` inverse uniquement si on a besoin de parcourir les objets liés depuis l'autre entité (par exemple `$client->getAppareils()`). Ce n'est pas une deuxième relation SQL.

**Exemple :** un client possède `0,N` appareils et chaque appareil appartient à `1,1` client. Dans `Appareil`, créer une relation `ManyToOne` vers `Client`, **non nullable**. Doctrine créera la colonne de clé étrangère dans la table des appareils.

## 5. Ajouter une relation avec `make:entity`

**Exemple — lier `Appareil` à `Client` :**
```bash
php bin/console make:entity Appareil
```
Répondre aux questions :

| Question | Réponse |
| --- | --- |
| Nom de la nouvelle propriété | `client` |
| Type | `relation` |
| Entité liée | `Client` |
| Type de relation | `ManyToOne` |
| Relation nullable ? | `no` (`1,1` côté appareil) |
| Ajouter la propriété inverse dans `Client` ? | `yes` (ce sera utile pour retrouver les appareils d'un client) |
| Nom de la propriété inverse (si demandé) | `appareils` |
| Suppression automatique des objets orphelins (`orphanRemoval`) ? | `no` par défaut, tant que la règle métier n'est pas définie |

**Résultat :** `Appareil` possède une propriété `$client`. Si on a ajouté le côté inverse, `Client` possède une collection `$appareils`.

> **Ne pas confondre `nullable` et suppression.** `nullable: no` impose qu'un appareil ait un client ; cela ne signifie pas qu'il faut supprimer les appareils quand on supprime le client. Ne pas activer de suppression en cascade sans décision métier explicite.

## 6. Les relations à créer pour Meditech Care

Traduction des **six associations actuellement visibles dans l'onglet MCD** :

| Association du MCD | Propriété à créer | Type | Nullable ? |
| --- | --- | --- | --- |
| Client **possède** Appareil (`0,N` ↔ `1,1`) | `Appareil.client` → `Client` | `ManyToOne` | **Non** |
| Appareil **concerne** Intervention (`0,N` ↔ `1,1`) | `Intervention.appareil` → `Appareil` | `ManyToOne` | **Non** |
| Employé **réalise** Intervention (`0,N` ↔ `0,1`) | `Intervention.employe` → `Employe` | `ManyToOne` | **Oui** |
| Intervention **utilise** MaterielIntervention (`0,N` ↔ `1,1`) | `MaterielIntervention.intervention` → `Intervention` | `ManyToOne` | **Non** |
| Employé **possède** CreneauTravail (`0,N` ↔ `1,1`) | `CreneauTravail.employe` → `Employe` | `ManyToOne` | **Non** |
| Employé **possède** Indisponibilite (`0,N` ↔ `1,1`) | `Indisponibilite.employe` → `Employe` | `ManyToOne` | **Non** |

Pour chacune de ces relations, **réutiliser l'étape 5** en changeant l'entité, la propriété et la réponse `nullable`.

**Sens des noms :** `Intervention.appareil` signifie « retrouver l'appareil concerné par cette intervention ». Si une collection inverse est utile, choisir un nom au pluriel, par exemple `Appareil.interventions`, `Employe.interventions`, `Intervention.materielsIntervention` ou `Employe.indisponibilites`.

## 7. Vérifier les entités avant de toucher à la BDD

**Vérifier les fichiers créés :**
```text
src/Entity/

src/Repository/
```
Pour chaque relation, contrôler :

- Le nom de l'entité liée et le sens de la relation.

- La valeur `nullable` en fonction du MCD.

- Les éventuelles collections inverses : utiles ou non selon le code à écrire.

- L'absence de propriété de clé étrangère créée manuellement en doublon.

**Contrôler le mapping Doctrine :**
```bash
php bin/console doctrine:schema:validate
```
> Cette vérification peut signaler un écart avec la base actuelle : c'est normal si les entités viennent d'être modifiées et que les migrations n'ont pas encore été appliquées. Lire le détail de l'erreur avant de poursuivre.

## 8. Générer puis exécuter les migrations

**Étape 1 — Générer une migration :**
```bash
php bin/console make:migration
```
Symfony compare les entités au schéma SQL actuel de la BDD, et prépare un fichier dans `migrations/`. **Ouvrir ce fichier et vérifier les modifications SQL prévues** : tables, colonnes, clés étrangères, suppression éventuelle de colonnes…

**Étape 2 — Appliquer la migration :**
```bash
php bin/console doctrine:migrations:migrate
```
Confirmer l'opération après avoir vérifié la migration. Les tables et relations sont maintenant créées ou modifiées en BDD.

**Étape 3 — Vérifier :**
```bash
php bin/console doctrine:schema:validate
```
Contrôler également les tables et clés étrangères dans l'outil SQL utilisé (phpMyAdmin, par exemple).

> **Notre règle :** utiliser les migrations, et non `doctrine:schema:update --force`, pour suivre les changements de BDD dans le projet Git. Ne pas appliquer une migration contenant des suppressions de données non comprises.

## 9. Générer le CRUD avec `make:crud`

**Une fois l'entité créée et la migration appliquée**, générer le CRUD correspondant. Exemple pour `Appareil` :
```bash
php bin/console make:crud Appareil
```
Cette commande génère les pages et le code permettant de **créer, consulter, modifier et supprimer** des appareils.

**Fichiers obtenus (entre autres) :**

| Fichier | Rôle |
| --- | --- |
| `src/Controller/AppareilController.php` | Gérer les routes et les actions du CRUD. |
| `src/Form/AppareilType.php` | Définir le formulaire de création / modification. |
| `templates/appareil/` | Afficher la liste, le détail et les formulaires Twig. |

**Tester le résultat :** lancer le serveur Symfony avec `symfony serve`, puis ouvrir la route du CRUD indiquée dans le contrôleur (généralement `/appareil/`). Vérifier les quatre actions.

**Notre méthode :** générer un CRUD pour chaque entité qui en a besoin, puis adapter les champs, routes et contrôles d'accès au projet. `make:crud` génère des **pages Twig classiques**, pas des endpoints d'API REST : pour notre backend API, il faudra adapter ou remplacer ces contrôleurs lorsque nous développerons les routes API.

## 10. Modifier une entité plus tard

**Ajouter ou corriger une propriété / une relation :**
```bash
php bin/console make:entity Appareil
```
`make:entity` permet de compléter une entité existante. Pour retirer ou modifier en profondeur une propriété déjà créée, adapter le code avec attention ; le générateur ne remplace pas toutes les modifications manuelles.

**Après chaque changement du mapping :**
```bash
php bin/console make:migration

php bin/console doctrine:migrations:migrate
```
**Ne pas oublier Git :** commiter les fichiers d'entités, de repositories et **la migration correspondante** dans la même branche de travail. Faire relire la PR selon notre workflow Git.

## 11. Problèmes courants : que faire ?

| Situation | Premier réflexe |
| --- | --- |
| « Je ne sais pas quel type de relation choisir » | Reprendre les deux cardinalités de l'association sur le MCD, puis consulter l'étape 4. |
| « Symfony me demande si la relation est nullable » | Regarder le minimum côté entité concernée : `0` = facultatif ; `1` = obligatoire. |
| « J'ai créé une propriété `fkClient` » | Ne pas la conserver en doublon : utiliser `relation` vers `Client`. |
| « Je veux retrouver tous les appareils d'un client » | Ajouter si nécessaire le côté inverse `Client.appareils`. |
| « `make:migration` veut supprimer une table » | **Ne pas exécuter**. Vérifier les entités, le mapping et la BDD ciblée. |
| « Le schéma n'est pas valide » | Lire le détail de `doctrine:schema:validate`, vérifier les relations et l'état des migrations. |

**---**

## Commandes complémentaires — à consulter au besoin

Ces commandes ne sont **pas nécessaires à chaque étape**.

| Commande | Utilité |
| --- | --- |
| `php bin/console make:entity` | Créer une entité ou ajouter des propriétés à une entité existante en mode interactif. |
| `php bin/console make:entity NomEntite` | Cibler directement une entité. |
| `php bin/console doctrine:mapping:info` | Lister les entités reconnues par Doctrine. |
| `php bin/console doctrine:schema:validate` | Vérifier le mapping et sa cohérence avec la BDD. |
| `php bin/console doctrine:migrations:status` | Consulter l'état des migrations. |
| `php bin/console doctrine:migrations:list` | Lister les migrations connues et leur exécution. |
| `php bin/console make:crud NomEntite` | Générer un CRUD avec contrôleur, formulaire et vues Twig pour une entité existante. |
| `php bin/console make:user` | Créer une entité d'utilisateur compatible avec le système de sécurité Symfony (à prévoir si `Employe` sert à se connecter). |

**Documentation officielle pour approfondir :** [Doctrine / BDD dans Symfony](https://symfony.com/doc/current/doctrine.html) · [Relations Doctrine](https://symfony.com/doc/current/doctrine/associations.html).

**---**

**Rappel final :** partir du **MCD validé**, créer les propriétés métier, traduire les cardinalités en relations Doctrine, contrôler le mapping, puis appliquer une migration vérifiée et générer les CRUD nécessaires. Ne pas ajouter de relations ou de règles métier absentes du modèle sans les faire valider.
