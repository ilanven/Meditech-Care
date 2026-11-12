# Git — Workflow et commandes de Meditech Care

> **But de cette page :** retrouver rapidement la bonne commande selon l'étape de notre workflow. Toutes les commandes sont à lancer depuis le dossier du projet, dans un terminal.

> Les exemples utilisent `feat/equipements` comme branche de travail. Remplacer ce nom par celui de la tâche en cours.

## 0. Le nommage de nos branches

| Branche | Rôle | Règle |
| --- | --- | --- |
| `main` | Versions importantes et fonctionnelles | Mise à jour peu fréquente, uniquement par PR depuis `develop`. |
| `develop` | Référence commune du développement | Mise à jour par PR validée, jamais de développement direct. |
| `feat/...` | Nouvelle fonctionnalité | Branche créée depuis `develop`. |
| `fix/...` | Correction d'un problème | Branche créée depuis `develop`. |
| `doc/...` | Documentation | Branche créée depuis `develop`. |

**Notre workflow :** `develop` → branche `feat/...` → Pull Request (PR) vers `develop` → revue par l'autre membre du binôme + CI → fusion → suppression de la branche.

**Livraison :** lorsqu'une version importante est prête, ouvrir une PR de **`develop` vers `main`**. Aucune autre branche ne doit faire de PR vers `main`.

**Trois repères :**

- **Local** : copie du projet sur l'ordinateur ; **distant** (`origin`) : dépôt GitHub.
- **Commit** : enregistrement d'une étape dans l'historique local ; **push** : envoi des commits sur GitHub.
- **PR** : proposition de fusion entre deux branches, permettant de relire le code avant validation.

## 1. Créer une nouvelle branche

**Avant de commencer :** vérifier qu'aucune modification en cours sur la branche actuelle ne risque d'être perdue ou déplacée.

```bash
git status
```

Si des modifications non commitées apparaissent, les enregistrer dans un commit **avant de changer de branche**. Si elles ne sont pas encore prêtes à être commitées (code à l'état de brouillon, par exemple), les mettre temporairement de côté avec un **stash** : voir la section [Changer de branche avec des modifications non commitées](#5-changer-de-branche-avec-des-modifications-non-commitées). Ne pas changer de branche machinalement.

**Étape 1 — Mettre `develop` à jour :**

```bash
git switch develop
git pull
```

**Étape 2 — Créer une branche et s'y placer :**

```bash
git switch -c feat/equipements
```

Utiliser `fix/...` pour une correction ou `doc/...` pour la documentation. Préférer des noms courts et explicites, en minuscules et avec des tirets.

**Vérifier la branche active :**

```bash
git branch --show-current
```

Le résultat doit correspondre à la nouvelle branche, **pas à `develop`**.

## 2. Coder et enregistrer son travail

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

Adapter le chemin aux fichiers modifiés. Utiliser `git add .` uniquement si **tous** les changements du dossier doivent être ajoutés. Vérifier d'abord avec `git status` pour éviter d'inclure des fichiers accidentellement.

**Envoyer la branche sur GitHub (première fois) :**

```bash
git push -u origin feat/equipements
```

**Pour les envois suivants, sur la même branche :**

```bash
git push
```

> Un commit n'a pas besoin de contenir toute une fonctionnalité. Faire plusieurs commits compréhensibles au fil du travail. Ne jamais commiter de secrets (`.env.local`, mots de passe, jetons…).

## 3. Intégrer les changements de `develop` pendant le travail

Si l'autre membre du binôme a fusionné du code dans `develop`, il peut être utile ou nécessaire de récupérer ces changements avant la fusion de la PR. **Rester sur sa branche de travail pour effectuer cette opération.**

**Précondition :** commiter les changements locaux ou les mettre temporairement de côté (voir [étape 5](#5-changer-de-branche-avec-des-modifications-non-commitées)).

```bash
git switch feat/equipements
git fetch origin
git merge origin/develop
```

- `fetch` : récupérer les nouvelles références distantes sans modifier les fichiers locaux.
- `merge origin/develop` : intégrer les modifications de `develop` dans **la branche actuellement active**.
- Si aucun conflit n'est signalé : vérifier le résultat, puis lancer `git push` si la branche a déjà été publiée.

**Si Git signale des conflits :**

1. Lancer `git status` pour repérer les fichiers concernés.
2. Ouvrir ces fichiers et choisir le contenu final correct. Supprimer les marqueurs `<<<<<<<`, `=======` et `>>>>>>>` s'ils apparaissent.
3. Vérifier le code et, si possible, lancer les tests.
4. Terminer la fusion :

   ```bash
   git add chemin/du/fichier-resolu.php
   git merge --continue
   git push
   ```

Si le merge est trop compliqué et que **la résolution des conflits n'a pas encore commencé**, revenir à l'état précédant le merge avec `git merge --abort` (voir les commandes complémentaires).

> **Actualiser sa branche avant chaque PR n'est pas obligatoire.** Cela devient important en cas de conflits, si les contrôles GitHub exigent une branche à jour ou si les nouvelles modifications sont nécessaires au travail en cours.

## 4. Terminer une fonctionnalité : Pull Request et fusion

1. **Vérifier les changements**, exécuter les contrôles disponibles et envoyer les derniers commits :

   ```bash
   git status
   git push
   ```

2. **Ouvrir une Pull Request sur GitHub** de **`feat/equipements` vers `develop`**. Attention au sens : *base* = `develop`, *compare* = la branche de travail.
3. **Décrire la modification** et demander une revue à l'autre membre du binôme.
4. **Attendre l'approbation de la PR** et la réussite des contrôles CI requis. Si des corrections sont demandées, les effectuer sur **la même branche**, puis créer de nouveaux commits et faire `git push` : la PR se met automatiquement à jour.
5. **Fusionner la PR sur GitHub** uniquement après la revue requise et les contrôles obligatoires. Supprimer ensuite la branche distante depuis GitHub, sauf si elle doit encore servir.

**Après la fusion, sur l'ordinateur :**

```bash
git switch develop
git pull
git branch -d feat/equipements
```

`git branch -d` supprime **uniquement la branche locale** et empêche certaines suppressions de branches non fusionnées, selon l'historique Git local. Si Git refuse alors que la PR a été fusionnée avec **Squash and merge**, vérifier d'abord que les modifications ont bien été intégrées. Ne pas forcer la suppression sans comprendre le message.

**Pour démarrer une nouvelle tâche :** reprendre à l'[étape 1](#1-créer-une-nouvelle-branche).

## 5. Changer de branche avec des modifications non commitées

**Toujours commencer par :**

```bash
git status
```

- **Aucun changement local :** utiliser directement `git switch nom-de-la-branche`.
- **Travail prêt à être enregistré :** faire un `git add`, puis un `git commit` sur la bonne branche.
- **Travail incomplet à mettre temporairement de côté :** utiliser `stash`:

**Mettre de côté les fichiers modifiés et les nouveaux fichiers non suivis (stash) :**

```bash
git stash push -u -m "Travail en cours..."
git switch develop
```

**Une fois l'interruption terminée, reprendre le travail :**

```bash
git switch feat/equipements
git stash pop
```

`stash pop` réapplique les modifications, puis retire le stash **si l'opération réussit**. En cas de conflit, résoudre celui-ci avant de poursuivre ; le stash peut être conservé.

> **Le stash n'est pas systématique.** Git peut parfois autoriser un changement de branche malgré des fichiers modifiés et déplacer ces modifications avec le changement de branche, ce qui peut prêter à confusion. Notre convention : autant que possible, changer de branche avec un répertoire de travail propre.

## 6. Problèmes courants : que faire ?

| Situation | Premier réflexe | Suite |
| --- | --- | --- |
| « Je ne sais plus sur quelle branche je suis » | `git status` | Vérifier la ligne `On branch ...` et les fichiers modifiés. |
| « Git refuse `switch` à cause de modifications locales » | `git status` | Faire un commit sur la bonne branche ou utiliser `stash` (étape 5), puis `switch`. |
| « `develop` a changé et ma PR a des conflits » | `git fetch origin` | Fusionner `origin/develop` dans la branche de travail et résoudre les conflits (étape 3). |
| « J'ai corrigé du code après avoir ouvert la PR » | Rester sur la branche de la PR | Faire un commit puis `git push` : inutile de créer une nouvelle PR. |
| « J'ai oublié si mes commits sont sur GitHub » | `git status` | Sur une branche suivie, vérifier si Git indique des commits en avance sur le distant. |
| « J'ai commité sur la mauvaise branche » | Arrêter les nouvelles commandes et consulter `git log -5 --oneline` | Éviter `reset --hard` : récupérer les commits proprement selon la situation. |

**En cas de doute :** ne lancer aucune commande destructrice pour « tout remettre à zéro ». Relever le résultat de `git status`, la commande qui a échoué et son message d'erreur pour pouvoir analyser le problème.

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
| `git switch develop` | Passer sur une branche existante. Commande à préférer à `git checkout develop`. |
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
| `git stash push -u -m "message"` | Sauvegarder les modifications suivies et les nouveaux fichiers non suivis, avec un libellé ; n'inclut pas les fichiers ignorés. |

### Pour plus tard : `rebase`

`git rebase origin/develop` permet de rejouer les commits de la branche de travail sur une version plus récente de `develop`. Cela peut rendre l'historique plus linéaire, **mais réécrit les commits** et demande davantage de précautions si la branche a déjà été publiée. **Notre workflow standard utilise `merge`, pas `rebase`.**

---

**Rappel final :** toutes les fonctionnalités, corrections et modifications documentaires partent de `develop` ; toute PR vers `develop` est relue par l'autre membre du binôme ; seule `develop` peut proposer une PR vers `main`.