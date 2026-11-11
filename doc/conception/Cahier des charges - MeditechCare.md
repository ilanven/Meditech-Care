# Cahier des charges — Meditech Care

**Version :** 2.0 — document de travail  
**Date :** 11 novembre 2026  
**Projet :** Application de gestion de maintenance d'équipements médicaux

## 1. Présentation du projet

Meditech Care est une entreprise spécialisée dans la maintenance d'équipements médicaux auprès d'établissements tels que des cliniques, cabinets médicaux et centres esthétiques.

Le projet consiste à développer une application web métier centralisant la gestion des clients, des appareils, des employés, des plannings, des interventions et des consommables.

L'application est réservée aux employés de Meditech Care. Les clients ne disposent pas de compte ni d'accès à l'application.

## 2. Objectifs

- Centraliser les informations relatives aux établissements clients, à leurs appareils et aux interventions effectuées sur ces appareils.
- Organiser les diagnostics et les opérations de maintenance des techniciens.
- Permettre la réalisation d'une maintenance immédiatement après le diagnostic ou à une date ultérieure, sans obligation de planification préalable.
- Gérer les disponibilités des techniciens et la planification ponctuelle des opérations.
- Suivre les stocks de consommables et leur utilisation réelle lors des interventions.
- Conserver les informations détaillées des interventions et des consommables utilisés.
- Proposer une interface adaptée aux administrateurs et aux techniciens.

## 3. Acteurs et accès

### 3.1 Employés

Les utilisateurs de l'application sont des employés de Meditech Care, occupant **un seul des deux postes** suivants :

- **Administrateur :** gère les clients, appareils, employés, plannings, interventions et stocks ; peut consulter les interventions et leurs comptes rendus.
- **Technicien :** consulte les interventions qui lui sont affectées, réalise les diagnostics et les maintenances, renseigne leurs informations et enregistre les consommables utilisés.

L'accès nécessite une authentification. Les autorisations sont contrôlées côté backend selon le poste de l'employé.

Les droits détaillés par action seront formalisés ailleurs.

### 3.2 Clients

Un client correspond à **un établissement ou à un service d'établissement** faisant appel à Meditech Care. Deux services d'un même établissement sont enregistrés comme **deux clients distincts**.

Chaque fiche client comporte un contact attribué, avec ses coordonnées. Le client n'est pas un utilisateur authentifié et ne peut pas accéder à l'application.

## 4. Périmètre fonctionnel

### 4.1 Gestion des clients

- Créer, consulter, modifier et supprimer des fiches clients.
- Enregistrer les informations du client (établissement/service) et du contact qui lui est attribué.
- Rechercher et consulter les clients.
- Consulter les appareils rattachés à un client.
- Accéder aux interventions passées du client **par l'intermédiaire de ses appareils**.

La suppression est, à ce stade, une opération classique. Une stratégie d'archivage logique figure dans le backlog (section 7).

### 4.2 Gestion des appareils

- Créer, consulter, modifier et supprimer une fiche appareil.
- Rattacher chaque appareil à un client.
- Enregistrer ses informations d'identification et ses caractéristiques techniques.
- Consulter ses interventions et leurs comptes rendus.

**Une intervention est rattachée à un appareil, pas directement à un client.** Le client concerné est déterminé par le rattachement de l'appareil.

### 4.3 Gestion des employés

- Créer, consulter, modifier et supprimer les fiches employés.
- Enregistrer leurs coordonnées professionnelles et informations de connexion.
- Attribuer à chaque employé le poste d'**Administrateur** ou de **Technicien**.
- Consulter les interventions affectées à un technicien.
- Gérer leurs temps de travail (voir section suivante : Gestion du planning).

### 4.4 Gestion du planning

- Créer, consulter, modifier et supprimer des **créneaux de travail ponctuels** associés à un technicien.
- Créer, consulter, modifier et supprimer des **indisponibilités ponctuelles** associées à un technicien.
- Consulter le planning des techniciens, les créneaux, les indisponibilités et les opérations programmées.
- Programmer les dates et durées des diagnostics et, si nécessaire, des maintenances.
- Modifier librement ces dates et durées, sous réserve de respecter les contraintes de planning.
- Affecter **un seul technicien** à chaque intervention.
- Empêcher les conflits de planning : pas de chevauchement incompatible entre interventions programmées, créneaux et indisponibilités.

Les **fixtures** fourniront des séries de créneaux ponctuels correspondant à des horaires fixes pour les techniciens.

### 4.5 Gestion des interventions : diagnostic puis maintenance

Une intervention est une **opération unique, rattachée à un appareil et affectée à un seul technicien**. Elle comprend obligatoirement deux phases, dans cet ordre : **diagnostic puis maintenance**.

#### 4.5.1 Création et diagnostic

- Créer une intervention relative à un appareil.
- Prévoir une **date et une durée de diagnostic**, utilisées pour la gestion du planning. La programmation du diagnostic peut être effectuée après la création de l’intervention.
- Réaliser le diagnostic en premier, avant toute opération de maintenance.
- Enregistrer le **problème identifié** à l'aide d'une liste de valeurs prédéfinies (*enum*), comprenant une catégorie **« Autres »** qui peut être précisée.
- Rédiger une **description détaillée** du diagnostic et de la situation constatée.
- Proposer une action **« Réaliser le diagnostic »** permettant de valider la réalisation du diagnostic.
- Si le diagnostic est effectué en dehors de la date et de l’heure programmées, permettre, lors de cette action, de remplacer `dateDiagnostic` par la date et l’heure actuelles. Sinon, conserver la valeur programmée.
- Conserver les informations renseignées lors du diagnostic. Le changement de statut indique que le diagnostic a été réalisé, sans attribut booléen supplémentaire dédié.

#### 4.5.2 Planification ou réalisation de la maintenance

Une fois le diagnostic réalisé, le technicien peut :

- **Effectuer immédiatement** la maintenance, sans programmation préalable ; ou
- **Programmer la maintenance plus tard**, à n'importe quel moment après le diagnostic, en renseignant une date et une durée estimée d'intervention.

La maintenance ne peut pas commencer avant que le diagnostic ait été effectué. Une maintenance n'a pas l'obligation d'avoir été planifiée à l'avance. Les dates et durées planifiées peuvent être ajustées, sous réserve des contraintes du planning.

#### 4.5.3 Réalisation et compte rendu

- Enregistrer la réalisation de la maintenance.
- Permettre au technicien de **modifier et compléter le champ `description` pendant ou après la maintenance**.
- Enregistrer les consommables réellement utilisés, avec leurs quantités.
- Actualiser l'état de l'intervention au fil de son déroulement.
- Consulter l'intervention complète : diagnostic, maintenance, description et consommables utilisés.
- Générer un document PDF récapitulatif de l'intervention.

#### 4.5.4 Statuts et déroulement

L’intervention utilise les statuts métier suivants :

| Statut                                               | Sens métier                                                                                                                                              |
| ---------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Diagnostic à planifier**                           | L’intervention est créée, sans diagnostic programmé.                                                                                                     |
| **Diagnostic planifié**                              | Le diagnostic est programmé avec une date et une durée.                                                                                                  |
| **Intervention à planifier**                         | Le diagnostic est réalisé, la maintenance n’est pas programmée.                                                                                          |
| **Intervention planifiée**                           | La maintenance est programmée avec une date et une durée.                                                                                                |
| **Intervention en cours**                            | La maintenance est en cours de réalisation.                                                                                                              |
| **Intervention terminée**                            | La maintenance de cette intervention est achevée, sans nouvelle intervention nécessaire.                                                                 |
| **Intervention à poursuivre** *(libellé provisoire)* | La maintenance de cette intervention a été effectuée, mais une nouvelle intervention est nécessaire. Cela induit la création d'une nouvelle intervention |
| **Intervention annulée**                             | L’intervention est annulée avant sa clôture.                                                                                                             |

Les transitions usuelles suivent l’ordre diagnostic puis maintenance. Cependant :

- Un diagnostic réalisé immédiatement peut passer directement de **« Diagnostic à planifier »** à **« Intervention à planifier »**, sans réservation préalable.
- Une maintenance réalisée immédiatement après le diagnostic peut commencer depuis **« Intervention à planifier »**, sans passage obligatoire par **« Intervention planifiée »**.
- Une reprogrammation de date ou de durée ne crée **pas** une nouvelle intervention et ne nécessite **pas** le statut « Intervention à poursuivre ».
- Une intervention qui nécessite une opération ultérieure peut être clôturée comme **« Intervention à poursuivre »**. La nouvelle intervention est créée séparément sur l’appareil concerné, **sans relation directe entre les deux interventions**.
- L’annulation peut intervenir avant la clôture ; les transitions exactes autorisées seront précisées lors de la définition des règles métier.

#### 4.5.5 Dates et durées

Les dates et durées du diagnostic et de la maintenance servent **principalement à construire et à gérer le planning**. Elles ne constituent pas un suivi détaillé du temps réellement passé sur le terrain.

- Elles peuvent être modifiées tant que les contraintes du planning sont respectées.
- Les durées renseignées sont des durées **prévues/estimées** : aucun suivi obligatoire de durée réelle n’est demandé.
- Pour le diagnostic, l’action **« Réaliser le diagnostic »** peut mettre `dateDiagnostic` à l’horodatage courant si la réalisation a lieu à un autre moment que celui prévu.
- Aucun historique des changements de dates ou de durées n’est imposé à ce stade.

### 4.6 Gestion des consommables et des stocks

- Créer, consulter, modifier et supprimer les références de consommables.
- Enregistrer la quantité disponible et le seuil minimal d'alerte.
- Consulter les quantités en stock.
- Signaler les références dont le stock est insuffisant.
- Enregistrer, pour chaque intervention, les consommables utilisés et leurs quantités.
- **Décrémenter le stock dès qu'une utilisation est enregistrée.**
- Conserver séparément les enregistrements de consommables utilisés dans une intervention et l'état actuel du stock.

Le matériel géré est **exclusivement consommable** : pas de gestion d'outils réutilisables ni de retour de prêt. Dans le cas d'un retour de matériel, il suffira d'ajuster manuellement le stock.

La saisie de consommation doit être cohérente avec les quantités disponibles. Les modalités de correction d'une consommation déjà saisie seront précisées lors de la conception des règles métier.

## 5. Exigences techniques

### 5.1 Architecture

- **Backend :** Symfony 8, API REST.
- **Frontend :** Svelte, application séparée consommant l'API REST.
- **Base de données :** MySQL.
- **Persistance :** Doctrine ORM et migrations.
- **Échanges :** requêtes HTTP(S) et données JSON.
- **Contrat de l'API :** documentation OpenAPI consultable via Swagger UI.
- **PDF :** génération côté serveur d'un récapitulatif d'intervention.

### 5.2 Sécurité

- Authentification des employés par identifiant et mot de passe.
- Vérification des permissions sur le backend selon le poste de l'employé.
- Validation des données entrantes et protection des mots de passe.
- Utilisation de HTTPS pour le déploiement.
- Prise en compte du RGPD et sauvegardes de la base de données.

Le mécanisme technique précis d'authentification API sera défini au moment de la conception.

### 5.3 Compatibilité et exploitation

- Interface responsive utilisable sur les navigateurs modernes. Dans un premier temps, l'interface sera conçue pour une utilisation sur ordinateurs et tablettes. 
- Utilisation de Git pour le versionnement et la collaboration.
- Documentation d'installation, de déploiement, de sauvegarde et de restauration.
- Organisation modulaire favorisant la maintenance et les évolutions.

## 6. Livrables attendus

- Application web comprenant le backend Symfony 8 et le frontend Svelte.
- API REST documentée avec OpenAPI / Swagger UI.
- Documents de conception à jour : cahier des charges, règles métier, Merise et UML utile.
- Scénarios de test et vérifications fonctionnelles.
- Documentation technique et documentation utilisateur.
- Procédures d'installation, de déploiement et de sauvegarde/restauration.
- Jeu de fixtures permettant notamment de disposer d'horaires de travail fixes simulés à partir de créneaux ponctuels.

## 7. Backlog — évolutions hors périmètre immédiat

- **Archivage logique des données :** ajouter un attribut `active` aux entités concernées ; lors d'un `DELETE` métier, passer `active` à `false` au lieu de supprimer physiquement l'enregistrement, afin de préserver les historiques.
- Prise de rendez-vous en ligne par les clients.
- Version mobile dédiée aux techniciens.
- Visualisation historique d'une intervention plus poussée et précise.

Les fonctions du backlog **ne sont pas à implémenter dans le périmètre actuel**.

## 8. Points restant à arbitrer

1. **Permissions :** établir les droits exacts de chaque poste (création, planification, annulation, modification après clôture, etc.).
2. **Stock :** préciser le traitement des erreurs de saisie, modifications et annulations de consommations, ainsi que la politique en cas de quantité insuffisante.
3. **Planning :** définir les règles de blocage exactes lors d'une planification et la conduite à tenir pour une opération exécutée sans réservation préalable.
4. **Statuts :** confirmer le libellé définitif d’« Intervention à poursuivre » et formaliser les transitions autorisées, notamment les possibilités d’annulation et de correction.
5. **Diagnostic :** préciser si l’horodatage de réalisation différent du créneau prévu est remplacé automatiquement ou après confirmation du technicien, ainsi que le seuil de comparaison de l’heure.

Ces arbitrages seront répercutés dans le MCD, le MLD, les règles métier et les contrats API.
