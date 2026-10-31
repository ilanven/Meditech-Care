# Git — Workflow et commandes de Meditech Care

> **But de cette page :** retrouver rapidement la bonne commande selon l'étape où l'on se trouve. Toutes les commandes sont à lancer depuis le dossier du projet, dans un terminal.
>
> Les exemples utilisent `feat/equipements` comme branche de travail. Remplacez ce nom par celui de votre tâche.

## 0. Comprendre nos branches

| Branche | Rôle | Règle |
| --- | --- | --- |
| `main` | Versions importantes et fonctionnelles | Mise à jour peu fréquente, uniquement par PR depuis `develop`. |
| `develop` | Référence commune du développement | Mise à jour par PR validée, jamais de développement direct. |
| `feat/...` | Nouvelle fonctionnalité | Créée depuis `develop`. |
| `fix/...` | Correction d'un problème | Créée depuis `develop`. |
| `doc/...` | Documentation | Créée depuis `develop`. |

**Circuit habituel :** `develop` → branche `feat/...`, `fix/...` ou `doc/...` → Pull Request (PR) vers `develop` → revue par l'autre membre + CI → fusion → suppression de la branche.

**Livraison :** quand une version importante est prête, ouvrir une PR **`develop` → `main`**. Aucune autre branche ne doit faire de PR vers `main`.

**Trois repères :**
- **Local** : votre copie sur l'ordinateur ; **distant** (`origin`) : le dépôt GitHub.
- **Commit** : enregistre une étape dans l'historique local ; **push** : envoie vos commits sur GitHub.
- **PR** : propose de fusionner une branche et permet de relire le code avant validation.

## 1. Je commence une nouvelle tâche

**Avant de commencer :** vérifiez que vous n'avez pas de modifications en cours à conserver sur la branche actuelle.

```bash
git status
```

Si des modifications non committées apparaissent, terminez-les par un commit **sur la branche de travail appropriée** ou reportez-vous à [Je dois changer de branche avec du travail en cours](#5-je-dois-changer-de-branche-avec-du-travail-en-cours). Ne changez pas de branche machinalement.

**Étape 1 — Mettre `develop` à jour :**

```bash
git switch develop
git pull --ff-only origin develop
```

`--ff-only` évite de créer involontairement un commit de fusion lors du `pull`. En cas d'erreur, ne forcez pas : vérifiez `git status` et demandez de l'aide si les historiques divergent.

**Étape 2 — Créer sa branche et s'y placer :**

```bash
git switch -c feat/equipements
```

Utilisez `fix/...` pour une correction ou `doc/...` pour la documentation. Préférez des noms courts, explicites, en minuscules et avec des tirets.

**Vérifier :**

```bash
git branch --show-current
```

Vous devez être sur votre nouvelle branche, **pas sur `develop`**.

## 2. Je code et j'enregistre mon travail

**Voir ce qui a changé :**

```bash
git status
git diff
```

**Enregistrer une étape cohérente :**

```bash
git add src/Controller/EquipementController.php
git commit -m "feat: ajout du contrôleur des équipements"
```

Adaptez le chemin aux fichiers modifiés. `git add .` peut être utilisé si **tous** les changements du dossier sont voulus ; contrôlez d'abord `git status` pour ne pas ajouter de fichiers accidentellement.

**Envoyer la branche sur GitHub (première fois) :**

```bash
git push -u origin feat/equipements
```

**Envois suivants, sur la même branche :**

```bash
git push
```

> Un commit n'a pas besoin de contenir toute une fonctionnalité. Faites plusieurs commits compréhensibles au fil du travail. Ne commitez pas de secrets (`.env.local`, mots de passe, jetons…).

## 3. `develop` a changé pendant que je travaillais

Si l'autre personne a fusionné du code dans `develop`, il peut être utile ou nécessaire de l'intégrer avant la fusion de votre PR. **Vous restez sur votre branche de travail.**

**Précondition :** faites un commit de vos changements locaux ou mettez-les temporairement de côté (voir [étape 5](#5-je-dois-changer-de-branche-avec-du-travail-en-cours)).

```bash
git switch feat/equipements
git fetch origin
git merge origin/develop
```

- `fetch` récupère les nouvelles références distantes sans modifier vos fichiers.
- `merge origin/develop` intègre les modifications de `develop` dans **votre branche actuelle**.
- Si Git ne signale aucun conflit, vérifiez le résultat et faites `git push` si votre branche est déjà publiée.

**Si Git signale des conflits :**

1. Tapez `git status` pour repérer les fichiers concernés.
2. Ouvrez ces fichiers et choisissez le contenu final correct. Supprimez les marqueurs `<<<<<<<`, `=======`, `>>>>>>>` s'ils apparaissent.
3. Vérifiez le code et, si possible, lancez les tests.
4. Terminez la fusion :

```bash
git add chemin/du/fichier-resolu.php
git merge --continue
git push
```

Si le merge est trop compliqué et que vous **n'avez pas encore commencé à résoudre les conflits**, vous pouvez revenir à l'état précédant le merge avec `git merge --abort` (voir les commandes complémentaires).

> **Une actualisation n'est pas obligatoire avant toute PR.** Elle devient importante si des conflits existent, si les vérifications GitHub exigent une branche à jour ou si les nouvelles modifications sont nécessaires à votre travail.

## 4. Ma fonctionnalité est prête : Pull Request et fusion

1. Vérifiez les changements, exécutez les contrôles disponibles et envoyez les derniers commits :

   ```bash
   git status
   git push
   ```

2. Sur GitHub, ouvrez une **Pull Request de `feat/equipements` vers `develop`** (attention au sens : *base* = `develop`, *compare* = votre branche).
3. Décrivez la modification et demandez une revue à l'autre membre du binôme.
4. Attendez la validation de la PR et la réussite des contrôles CI requis. Si des corrections sont demandées, effectuez-les sur **la même branche**, puis faites de nouveaux commits et `git push` : la PR se mettra à jour automatiquement.
5. Fusionnez la PR sur GitHub seulement après la revue requise et les contrôles obligatoires. Supprimez ensuite la branche distante depuis GitHub dans la plupart des cas.

**Après la fusion, sur votre ordinateur :**

```bash
git switch develop
git pull --ff-only origin develop
git branch -d feat/equipements
```

`git branch -d` supprime **uniquement la branche locale** et protège contre la suppression d'une branche non fusionnée selon l'historique local. Si Git refuse alors que la PR a été fusionnée avec **Squash and merge**, vérifiez d'abord que la PR est bien intégrée ; ne forcez pas la suppression sans comprendre le message.

**Pour démarrer une nouvelle tâche**, repartez de l'[étape 1](#1-je-commence-une-nouvelle-tache).

## 5. Je dois changer de branche avec du travail en cours

**Commencez toujours par :**

```bash
git status
```

- **Aucun changement local :** `git switch nom-de-la-branche` suffit.
- **Travail prêt à être enregistré :** préférez un `git add` puis un `git commit`, sur la bonne branche.
- **Travail incomplet à mettre temporairement de côté :** utilisez `stash`.

**Mettre de côté les fichiers modifiés et les nouveaux fichiers non suivis :**

```bash
git stash push -u -m "Travail en cours sur les équipements"
git switch develop
```

Quand vous avez terminé votre interruption :

```bash
git switch feat/equipements
git stash pop
```

`stash pop` réapplique les modifications puis retire le stash **si l'opération réussit**. En cas de conflit, résolvez-le avant de poursuivre ; le stash peut être conservé.

> **Le stash n'est jamais systématique.** Git peut parfois laisser changer de branche malgré des fichiers modifiés et transporter ces modifications avec vous : cela prête à confusion. Notre convention est de changer de branche avec un répertoire de travail propre autant que possible.

## 6. Problèmes courants : que faire ?

| Situation | Premier réflexe | Suite |
| --- | --- | --- |
| « Je ne sais plus sur quelle branche je suis » | `git status` | Vérifier la ligne `On branch ...` et les fichiers modifiés. |
| « Git refuse `switch` à cause de modifications locales » | `git status` | Commit sur la branche correcte ou `stash` (étape 5), puis `switch`. |
| « Mon `pull --ff-only` est refusé » | `git status` puis `git branch --show-current` | Ne pas forcer : vérifier si un commit local a été créé par erreur sur `develop`. |
| « `develop` a changé et ma PR a des conflits » | `git fetch origin` | Fusionner `origin/develop` dans sa branche et résoudre les conflits (étape 3). |
| « J'ai corrigé du code après avoir ouvert la PR » | Rester sur la branche de la PR | Commit puis `git push` : inutile de créer une nouvelle PR. |
| « J'ai oublié si mes commits sont sur GitHub » | `git status` | Sur une branche suivie, Git indique si des commits sont en avance sur le distant. |
| « J'ai committé sur la mauvaise branche » | Arrêter les nouvelles commandes et consulter `git log -5 --oneline` | Ne pas faire de `reset --hard` : récupérer les commits proprement selon la situation. |

**En cas de doute : ne lancez pas de commande destructrice pour « tout remettre à zéro ».** Montrez le résultat de `git status`, la commande qui a échoué et son message d'erreur.

---

## Commandes complémentaires — à consulter au besoin

Ces commandes ne sont **pas** nécessaires à chaque étape du workflow.

### Consulter l'état et l'historique

| Commande | Utilité |
| --- | --- |
| `git status` | Voir la branche, les changements locaux et l'état du suivi distant. |
| `git branch` | Lister les branches locales (`*` = branche active). |
| `git branch -a` | Lister les branches locales et distantes connues. |
| `git log --oneline -5` | Voir les cinq derniers commits. |
| `git diff --staged` | Vérifier les changements ajoutés avec `git add`, avant le commit. |
| `git fetch origin` | Récupérer les informations récentes de GitHub, sans fusionner. |

### Comprendre les différentes commandes

| Commande | À quoi elle sert |
| --- | --- |
| `git switch develop` | Passer sur une branche existante. Commande préférée à `git checkout develop`. |
| `git switch -c feat/nom` | Créer une branche à partir de la branche actuelle et s'y placer. |
| `git checkout` | Ancienne commande polyvalente (branches et fichiers). Préférer `switch` pour les branches et `restore` pour les fichiers. |
| `git restore fichier.php` | **Annuler les modifications non indexées** de ce fichier ; attention, elles sont perdues. |
| `git merge --abort` | Abandonner une fusion en cours et tenter de retrouver l'état d'avant la fusion. |
| `git branch -d feat/nom` | Supprimer une branche **locale**, en vérifiant son intégration selon l'historique. |
| `git push -u origin feat/nom` | Publier une branche et configurer son suivi distant. |

### Stash : options supplémentaires

| Commande | Utilité |
| --- | --- |
| `git stash list` | Lister les travaux temporairement mis de côté. |
| `git stash apply` | Réappliquer le dernier stash sans le supprimer. |
| `git stash pop` | Réappliquer le dernier stash et le supprimer si cela réussit. |
| `git stash drop` | Supprimer un stash **sans le réappliquer** : à utiliser avec précaution. |
| `git stash push -u -m "message"` | Sauvegarder les modifications suivies et nouveaux fichiers non suivis, avec un libellé ; n'inclut pas les fichiers ignorés. |

### Pour plus tard : `rebase`

`git rebase origin/develop` permet de rejouer les commits de votre branche sur une version plus récente de `develop`. Cela peut rendre l'historique plus linéaire, **mais réécrit les commits** et demande davantage de précautions si la branche a déjà été publiée. **Notre workflow standard utilise `merge`, pas `rebase`.**

---

**Rappel final :** toutes les fonctionnalités, corrections et modifications documentaires partent de `develop` ; toute PR vers `develop` est relue par l'autre membre ; seule `develop` peut proposer une PR vers `main`.
