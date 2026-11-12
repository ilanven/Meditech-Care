# Règles de gestion — Meditech Care

**Version :** 1 — document de travail  
**Date :** 11 novembre 2026  
**Référence fonctionnelle :** `Meditech_Care_Cahier_des_charges.md` (version 2.0 du 11 novembre 2026)  
**Périmètre :** application métier Meditech Care, backend Symfony 8 / API REST et frontend Svelte.

> Ce document formalise les décisions métier prises à ce jour. Il décrit ce que l'application doit autoriser, interdire ou vérifier, indépendamment des détails d'implémentation. Les décisions non prises sont explicitement signalées en fin de document ; elles ne doivent pas être assimilées à des règles validées.

## 1. Conventions et vocabulaire

- **Client :** établissement ou service d'un établissement. Deux services du même établissement constituent deux clients distincts.
- **Contact :** personne de contact unique renseignée sur la fiche d'un client ; le contact ne possède pas de compte utilisateur.
- **Appareil :** équipement médical rattaché à un client.
- **Employé :** utilisateur authentifié occupant un seul poste, **Administrateur** ou **Technicien**.
- **Intervention :** dossier métier unique relatif à un appareil, comportant un diagnostic puis une maintenance. Le terme *opération* désigne selon le contexte la phase de diagnostic ou celle de maintenance.
- **Opération programmée :** phase disposant d'une date et d'une durée réservées dans le planning.
- **Consommable :** référence de matériel stocké pouvant être utilisée lors d'une maintenance ; aucun prêt d'outil réutilisable n'est géré.
- **Consommation :** quantité d'un consommable effectivement enregistrée comme utilisée pour une intervention.
- **`MaterielStock` :** catalogue des références de consommables et quantités disponibles, modifiable et supprimable indépendamment des traces passées.
- **`MaterielIntervention` :** trace autonome des consommables utilisés pour une intervention ; les informations historiques nécessaires restent conservées même si `MaterielStock` change ou disparaît.

Chaque règle est identifiée par un code stable `RG-XXX-NNN` pour faciliter les références dans la conception, les tests et la documentation API.

## 2. Clients et appareils

### 2.1 Clients

- **RG-CLI-001 — Nature du client.** Un client correspond à un établissement ou à un service d'un établissement. Deux services d'un même établissement sont enregistrés comme deux clients distincts.
- **RG-CLI-002 — Contact unique.** Chaque client dispose d'un contact attribué et de ses coordonnées. La gestion de plusieurs contacts par client n'entre pas dans le périmètre actuel.
- **RG-CLI-003 — Client sans appareil.** La création d'un client n'exige pas la création simultanée d'un appareil. Un client peut ne posséder aucun appareil.
- **RG-CLI-004 — Accès des clients.** Les clients et leurs contacts ne peuvent pas se connecter à l'application.
- **RG-CLI-005 — Suppression protégée.** La suppression physique d'un client est refusée tant que des appareils ou d'autres données conservées le référencent. Aucune suppression en cascade de ces données ne doit être déclenchée automatiquement.
- **RG-CLI-006 — Historique indirect.** Les interventions d'un client sont consultées par l'intermédiaire des appareils rattachés à ce client ; une intervention ne possède pas de rattachement direct au client.

### 2.2 Appareils

- **RG-APP-001 — Rattachement.** Chaque appareil est rattaché à un client.
- **RG-APP-002 — Changement de client.** Le rattachement d'un appareil à un autre client peut être modifié.
- **RG-APP-003 — Suppression protégée.** La suppression physique d'un appareil est refusée tant que des interventions ou d'autres données conservées le référencent. Les interventions associées ne doivent pas être supprimées en cascade.
- **RG-APP-004 — Historique par appareil.** L'ensemble des interventions enregistrées pour un appareil peut être consulté depuis sa fiche, indépendamment de leur statut.

> **Conséquence à connaître :** en l'absence de suivi historique des rattachements, changer le client d'un appareil modifie le client retrouvé par les interventions passées de cet appareil. La conservation du client *au moment de l'intervention* n'est pas actuellement définie (voir § 9).

## 3. Employés, accès et permissions

### 3.1 Règles générales

- **RG-EMP-001 — Authentification.** L'accès à l'application est réservé aux employés authentifiés.
- **RG-EMP-002 — Poste unique.** Chaque employé occupe un et un seul poste parmi `Administrateur` et `Technicien`.
- **RG-EMP-003 — Changement de poste.** Un administrateur peut modifier le poste d'un employé sans recréer son compte.
- **RG-EMP-004 — Administration des référentiels.** La gestion des clients, appareils, employés, créneaux de travail, indisponibilités et références/quantités de stock est réservée aux administrateurs.
- **RG-EMP-005 — Consultation des interventions.** Un technicien peut consulter uniquement les interventions qui lui sont affectées. Un administrateur peut consulter l'ensemble des interventions.
- **RG-EMP-006 — Droits techniques des administrateurs.** Un administrateur dispose également des droits nécessaires à la réalisation d'un diagnostic et d'une maintenance. Lorsqu'il exécute une opération, il figure lui-même comme employé affecté à l'intervention, selon le même rattachement qu'un technicien.
- **RG-EMP-007 — Autorisations côté serveur.** Les permissions sont vérifiées par le backend, indépendamment des contrôles d'affichage du frontend.
- **RG-EMP-008 — Désactivation des employés référencés.** Un employé possédant des références (notamment créneaux ou interventions) ne peut pas être supprimé physiquement : il est désactivé par `active = false`, uniquement s’il ne possède aucune opération future planifiée. Les références historiques sont conservées.
- **RG-EMP-009 — Compte désactivé.** Un employé désactivé n'a plus accès à l'application.

### 3.2 Création, affectation et annulation des interventions

- **RG-AUT-001 — Création ordinaire.** La création d'une intervention ordinaire est réservée à l'administrateur.
- **RG-AUT-002 — Exception pour maintenance à refaire.** Un technicien peut créer une **nouvelle intervention** lorsque la maintenance d'une intervention précédente nécessite une opération supplémentaire. Cette création reste une nouvelle intervention indépendante ; aucun lien direct entre les deux enregistrements n'est imposé.
- **RG-AUT-003 — Affectation.** Seul un administrateur peut attribuer ou réattribuer un technicien à une intervention.
- **RG-AUT-004 — Annulation.** Un administrateur peut annuler une intervention ; un technicien peut également annuler une intervention à laquelle il a accès (voir RG-EMP-005).
- **RG-AUT-005 — Corrections après clôture.** Un technicien peut corriger la description et les consommations des interventions terminées auxquelles il a accès. Les droits d’administrateur incluent cette capacité technique.
- **RG-AUT-006 — Replanification par le technicien.** Un technicien peut modifier les dates et durées des phases planifiées des interventions qui lui sont affectées, sous réserve de respecter les contraintes du planning. La première affectation et la réaffectation restent réservées à l’administrateur.

### 3.3 Matrice synthétique des permissions

| Action                                                          | Administrateur | Technicien                     |
| --------------------------------------------------------------- |:--------------:|:------------------------------:|
| Gérer les clients et leurs contacts                             | Oui            | Non                            |
| Gérer les appareils                                             | Oui            | Non                            |
| Gérer les employés et leurs postes                              | Oui            | Non                            |
| Gérer les créneaux et indisponibilités                          | Oui            | Non                            |
| Gérer les références et quantités de stock                      | Oui            | Non                            |
| Consulter toutes les interventions                              | Oui            | Non                            |
| Consulter les interventions affectées à soi-même                | Oui            | Oui                            |
| Créer une intervention ordinaire                                | Oui            | Non                            |
| Créer une nouvelle intervention pour poursuivre une maintenance | Oui            | Oui                            |
| Attribuer / réattribuer un technicien                           | Oui            | Non                            |
| Replanifier une opération d'une intervention accessible         | Oui            | Oui, si elle lui est affectée  |
| Désactiver un employé référencé, sous conditions                | Oui            | Non                            |
| Annuler une intervention accessible                             | Oui            | Oui                            |
| Effectuer un diagnostic / une maintenance                       | Oui            | Oui, sur intervention affectée |
| Corriger description / consommations après clôture              | Oui            | Oui, sur intervention affectée |

**Précision :** le technicien peut replanifier ses propres interventions, mais ne peut pas affecter ou réaffecter un employé. Un administrateur réalisant une opération doit être l’employé affecté. Les autres détails d’autorisation non encore précisés seront traités lors de la conception des cas d’utilisation et de l’API.

## 4. Planning et disponibilités

- **RG-PLA-001 — Créneaux ponctuels.** Les créneaux de travail sont enregistrés individuellement pour un technicien ; aucune récurrence native n'est imposée.
- **RG-PLA-002 — Indisponibilités ponctuelles.** Les indisponibilités sont enregistrées individuellement pour un technicien.
- **RG-PLA-003 — Jeux de données.** Les fixtures peuvent créer des séries de créneaux ponctuels afin de simuler les horaires fixes de chaque technicien.
- **RG-PLA-004 — Dépassement de créneau.** Une opération programmée peut dépasser la fin d'un créneau de travail. Il n'est donc pas exigé que sa durée entière soit comprise dans ce créneau. Ce dépassement devra être signalé lors de l'affectation de l'opération.
- **RG-PLA-005 — Non-chevauchement.** Une planification d'une opération ne doit pas produire de conflit/cheveauchement avec d'autres opérations affectées au même technicien, ni avec ses indisponibilités.
- **RG-PLA-006 — Création d'indisponibilité.** Une indisponibilité ne peut pas être créée sur une période déjà occupée par une opération planifiée incompatible ; cette opération doit être déplacée auparavant.
- **RG-PLA-007 — Protection des créneaux utilisés.** Il est interdit de modifier ou supprimer un créneau de travail tant qu'il contient des opérations programmées.
- **RG-PLA-008 — Planification dans le passé.** Il est interdit de programmer une nouvelle opération dans le passé, sauf dans le cadre d'une correction ou de l'enregistrement d'une opération effectivement réalisée.
- **RG-PLA-009 — Réalisation immédiate.** Une opération réalisée sans réservation préalable doit néanmoins respecter les règles applicables de créneaux de travail et d’indisponibilité, **sauf l’exception de validation du diagnostic prévue par RG-DIA-008**.
- **RG-PLA-010 — Modification des horaires.** Les dates et durées de planification peuvent être modifiées dès lors que les contraintes du planning applicables sont respectées ; un technicien peut replanifier les opérations de ses propres interventions (RG-AUT-006).
- **RG-PLA-011 — Un technicien par intervention.** Une intervention ne peut avoir qu'un seul technicien affecté à la fois. Elle peut cependant être créée avant toute affectation.
- **RG-PLA-012 — Finalité des durées.** Les durées renseignées sont estimées ou prévues, et non des mesures obligatoires du temps réel passé. Aucun historique des modifications horaires n'est exigé.

> **À préciser :** le critère exact d'acceptation d'un dépassement (par exemple, heure de début située dans un créneau), les règles de contrôle d'une opération immédiate et l'étendue des conflits sont encore à arrêter (§ 9). Le principe de dépassement autorisé ne supprime pas la règle d'absence de conflits.

## 5. Cycle de vie des interventions

### 5.1 Création et rattachement

- **RG-INT-001 — Appareil obligatoire.** Chaque intervention concerne exactement un appareil. Le client est déduit du rattachement de cet appareil.
- **RG-INT-002 — Affectation différée.** Une intervention peut être créée sans technicien ; un administrateur procède à son affectation par la suite au moment de la planification du diagnostic.
- **RG-INT-003 — Deux phases ordonnées.** Une intervention comprend le diagnostic, puis la maintenance. La maintenance ne peut commencer avant validation du diagnostic.
- **RG-INT-004 — Unicité du dossier.** Les deux phases d'un même dossier constituent une seule intervention métier, et non deux interventions distinctes.
- **RG-INT-005 — Absence de chaînage.** Si une maintenance complémentaire est nécessaire, elle est saisie comme une nouvelle intervention indépendante concernant l'appareil. Aucun lien direct entre interventions successives n'est conservé (pour l'instant).

### 5.2 Diagnostic

- **RG-DIA-001 — Diagnostic éventuellement non programmé.** Une intervention nouvellement créée peut ne pas disposer de date ni de durée de diagnostic ; son statut est alors « Diagnostic à planifier ».
- **RG-DIA-002 — Diagnostic planifié.** Lorsqu'une date et une durée estimée de diagnostic sont renseignées pour programmer l'opération, le statut correspondant est « Diagnostic planifié ». A ce stade, un employé doit avoir été affecté à l'intervention.
- **RG-DIA-003 — Réalisation sans programmation.** Un diagnostic peut être réalisé directement sans passer par le statut « Diagnostic planifié ». Les règles spécifiques à la validation du diagnostic s’appliquent (RG-DIA-008).
- **RG-DIA-004 — Plusieurs problèmes.** Un diagnostic peut identifier **plusieurs problèmes**, sélectionnés parmi les valeurs prédéfinies. Leur nomenclature métier devra être fixée avant l'implémentation.
- **RG-DIA-005 — Catégorie « Autres ».** Si la catégorie « Autres » de problème est sélectionnée, une précision textuelle est obligatoire.
- **RG-DIA-006 — Description.** Le diagnostic comporte une description détaillée, qui peut être complétée et modifiée ultérieurement.
- **RG-DIA-007 — Validation explicite.** L'action « Réaliser le diagnostic » valide la réalisation de cette phase. Le statut de l'intervention permet de constater cette validation ; aucun booléen `diagnosticRealise` distinct n'est requis.
- **RG-DIA-008 — Diagnostic hors créneau prévu.** Lors du clic sur « Réaliser le diagnostic », si le diagnostic est réalisé **en dehors de son créneau attribué**, `dateDiagnostic` est automatiquement remplacée par la date et l’heure du clic et `dureeDiagnostic` est mise à `0`. Cette durée nulle retire l’opération du planning ; la mise à jour automatique est autorisée **sans contrôle des contraintes de planning**. Lors de la saisie du diagnostic, le technicien peut ensuite modifier la date et la durée. Dès qu’une durée positive rend le diagnostic visible dans le planning, les contraintes habituelles de planification doivent être respectées. Si le diagnostic est réalisé dans son créneau attribué, les valeurs prévues sont conservées.
- **RG-DIA-009 — Correction autorisée.** Les problèmes identifiés et la description du diagnostic peuvent être modifiés après validation, sans imposer un nouveau diagnostic.

### 5.3 Maintenance

- **RG-MAI-001 — Diagnostic préalable.** Le démarrage d'une maintenance est refusé tant que le diagnostic n'a pas été validé.
- **RG-MAI-002 — Maintenance différée.** Après diagnostic, la maintenance peut être programmée pour plus tard avec une date et une durée estimée.
- **RG-MAI-003 — Maintenance immédiate.** Après diagnostic, la maintenance peut être commencée sans programmation préalable et sans date ni durée préalablement renseignées, sous réserve des contrôles de planning applicables.
- **RG-MAI-004 — Description modifiable.** La description de l'intervention peut être complétée ou corrigée pendant et après la maintenance.
- **RG-MAI-005 — Clôture standard.** Lorsque la maintenance est achevée et qu'aucune opération supplémentaire n'est requise, l'intervention prend le statut « Intervention terminée ».
- **RG-MAI-006 — Maintenance à poursuivre.** Lorsque la maintenance effectuée nécessite une nouvelle intervention, l'intervention courante peut être clôturée avec le statut provisoirement nommé « Intervention à poursuivre ».
- **RG-MAI-007 — Création différée.** L'attribution du statut « Intervention à poursuivre » n'oblige pas à créer immédiatement la nouvelle intervention ; elle pourra être créée ultérieurement.
- **RG-MAI-008 — Annulation après démarrage.** Une intervention peut être annulée même après le démarrage de la maintenance, sous réserve des droits d'accès correspondants.
- **RG-MAI-009 — PDF.** Un récapitulatif PDF de l'intervention peut être généré à partir des informations enregistrées pour ce dossier.

### 5.4 Statuts métier

| Statut                                               | Signification                                                    |
| ---------------------------------------------------- | ---------------------------------------------------------------- |
| **Diagnostic à planifier**                           | Intervention créée, diagnostic non programmé                     |
| **Diagnostic planifié**                              | Diagnostic programmé avec date et durée                          |
| **Intervention à planifier**                         | Diagnostic réalisé, maintenance non programmée                   |
| **Intervention planifiée**                           | Maintenance programmée avec date et durée                        |
| **Intervention en cours**                            | Maintenance commencée                                            |
| **Intervention terminée**                            | Maintenance achevée, sans nouvelle intervention nécessaire       |
| **Intervention à poursuivre** *(libellé provisoire)* | Maintenance effectuée, nouvelle intervention nécessaire          |
| **Intervention annulée**                             | Intervention annulée, y compris après le début de la maintenance |

- **RG-STA-001 — Progression normale.** Les statuts traduisent les étapes du diagnostic puis de la maintenance.
- **RG-STA-002 — Saut de planification du diagnostic.** `Diagnostic à planifier` peut évoluer directement vers `Intervention à planifier` après réalisation du diagnostic.
- **RG-STA-003 — Saut de planification de maintenance.** `Intervention à planifier` peut évoluer directement vers `Intervention en cours` lorsque la maintenance commence immédiatement.
- **RG-STA-004 — Replanification simple.** Modifier la date ou la durée d'une opération planifiée ne crée pas une nouvelle intervention et ne correspond pas au statut « Intervention à poursuivre ».
- **RG-STA-005 — Annulation.** L'annulation est autorisée à n'importe quel stade de l'intervention, sauf pour les statuts "Intervention terminée" et "Intervention à poursuivre".
- **RG-STA-006 — États de clôture.** « Intervention terminée » et « Intervention à poursuivre » expriment deux issues différentes d'une maintenance réalisée.

**Parcours standard indicatif :**  
`Diagnostic à planifier → Diagnostic planifié → Intervention à planifier → Intervention planifiée → Intervention en cours → Intervention terminée / Intervention à poursuivre`.

Les états de planification peuvent être sautés dans les cas de réalisation immédiate. Le statut « Intervention annulée » correspond à une sortie possible selon les règles d'annulation. Ce parcours n'est **pas encore** une matrice exhaustive des transitions permises.

## 6. Consommables et stocks

- **RG-STO-001 — Consommables seulement.** Le stock gère exclusivement des références consommables ; les outils réutilisables et les prêts ne sont pas inclus.
- **RG-STO-002 — Quantité et seuil.** Chaque référence gérée possède une quantité disponible et un seuil minimal d'alerte.
- **RG-STO-003 — Alerte non bloquante.** Un stock inférieur au seuil minimal déclenche une alerte mais ne bloque pas, en lui-même, une utilisation.
- **RG-STO-004 — Pas de stock négatif.** Une consommation supérieure à la quantité disponible est refusée.
- **RG-STO-005 — Décrémentation immédiate.** Enregistrer une quantité effectivement utilisée pour une intervention décrémente immédiatement d'autant la quantité disponible en stock.
- **RG-STO-006 — Traçabilité distincte.** `MaterielIntervention` enregistre de manière autonome les informations sur les consommables utilisés lors de chaque intervention. `MaterielStock` représente le catalogue et les quantités actuellement disponibles. Modifier ou supprimer une référence `MaterielStock` ne doit pas altérer les informations déjà enregistrées dans `MaterielIntervention`.
- **RG-STO-007 — Correction de quantité.** Modifier une consommation enregistrée ajuste le stock selon la différence entre l'ancienne quantité et la nouvelle. Toute augmentation reste soumise à RG-STO-004.
- **RG-STO-008 — Suppression de consommation.** La suppression d'une ligne de consommation restitue automatiquement la quantité précédemment enregistrée au stock.
- **RG-STO-009 — Saisie après clôture.** Une consommation peut être saisie ou corrigée pendant ou après la maintenance, y compris après la clôture de l'intervention, selon les permissions applicables.
- **RG-STO-010 — Suppression de référence autorisée.** Une référence `MaterielStock` peut être modifiée ou supprimée même si elle a déjà été utilisée. Les lignes de `MaterielIntervention` correspondantes restent intactes et consultables, sans dépendre de l’existence de la référence de stock supprimée.
- **RG-STO-012 — Conservation après annulation.** Lorsqu'une intervention est annulée après le début de sa maintenance, les consommations déjà enregistrées sont conservées et restent modifiables selon les droits applicables. L’annulation ne déclenche pas automatiquement leur restitution en stock.
- **RG-STO-011 — Retour physique.** Un éventuel retour de matériel ne relève pas d'une procédure de prêt : il se traduit, dans le périmètre actuel, par un ajustement manuel du stock.

**Exemple de correction (illustratif) :** pour un stock disponible de 12 unités, une consommation de 3 unités le porte à 9. Si la consommation est corrigée de 3 à 2, le stock remonte à 10. Supprimer cette ligne de consommation restituerait les 2 unités restantes, et le stock reviendrait à 12, sauf autres mouvements intervenus entre-temps.

## 7. Contraintes transversales

- **RG-TRA-001 — Contrôle côté backend.** Les contraintes de permission, d'existence des références, de planning, de statut et de stock sont vérifiées côté serveur pour toute opération concernée.
- **RG-TRA-002 — Cohérence des données.** Une opération refusée pour non-respect d'une règle métier ne doit pas laisser de modification partielle incohérente (par exemple : consommation enregistrée sans décrémentation correspondante, ou inversement).
- **RG-TRA-003 — Identifiants et rattachements.** Les données liées doivent désigner des enregistrements existants, conformément aux règles de rattachement et de suppression définies dans ce document.
- **RG-TRA-004 — Règles d'accès sur les données.** Le fait de connaître l'identifiant d'une ressource ne doit pas permettre à un technicien d'accéder à une intervention qui ne lui est pas affectée.
- **RG-TRA-005 — Protection des comptes.** Les mots de passe des employés sont protégés ; ils ne sont pas exposés dans les réponses de l'API.

## 8. Backlog hors périmètre actuel

- **Archivage logique généralisé :** extension future de l’attribut `active` à d’autres entités. **Exception déjà retenue dans le périmètre courant : désactivation des employés référencés (RG-EMP-008).**
- Prise de rendez-vous en ligne par les clients.
- Application mobile dédiée aux techniciens.
- Historique plus fin des modifications et du déroulement des interventions.

L’archivage logique généralisé reste hors périmètre. En revanche, la désactivation des employés référencés avec `active = false` fait désormais partie des règles actuelles.

## 9. Points encore à arbitrer avant de finaliser MCD et contrats API

Les éléments suivants **ne sont pas des décisions validées**. Ils sont conservés ici pour éviter des interprétations différentes pendant le développement.

| Réf.      | Question restant à trancher                                                                                                                                                                                                                              | Impact                     |
| --------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------- |
| **PA-01** | Lorsqu’un appareil change de client, faut-il conserver le client historique de ses anciennes interventions ou accepter qu’elles apparaissent sous le nouveau client ?                                                                                    | MCD / historique           |
| **PA-03** | Préciser les règles exactes de planning : condition minimale de couverture par un créneau malgré les dépassements, définition des conflits et contrôles des maintenances immédiates. L’exception applicable au diagnostic est déjà fixée par RG-DIA-008. | Planning / validations     |
| **PA-07** | Définir la nomenclature des problèmes sélectionnables et déterminer comment renseigner « Autres » lorsque plusieurs problèmes sont sélectionnés.                                                                                                         | Modélisation des problèmes |
| **PA-09** | Lorsqu’un technicien crée une intervention pour maintenance complémentaire, comment vérifier cette exception sans lien direct entre interventions successives ?                                                                                          | Autorisations / API        |

**Arbitrages clos en version 1.1 :** PA-02 (séparation `MaterielStock` / `MaterielIntervention`), PA-04 (replanification par le technicien), PA-05 (administrateur affecté lorsqu’il intervient), PA-08 (horodatage et durée nulle du diagnostic hors créneau), PA-10 (désactivation conditionnelle des employés référencés), PA-11 (consommations conservées et modifiables après annulation).

## 10. Suivi des évolutions

| Version | Date       | Modification                                                                                                                                                         |
| ------- | ---------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1.0     | 11/11/2026 | Première formalisation à partir du cahier des charges V2 et des décisions de cadrage : clients, appareils, employés, permissions, planning, interventions et stocks. |
| 1.1     | 11/11/2026 | Intégration des décisions PA-02, PA-04, PA-05, PA-08, PA-10 et PA-11 ; adaptation des règles et de la matrice des permissions.                                       |

---

**Statut :** V1.1 de travail. Les règles numérotées reflètent les choix formulés ; seuls les arbitrages encore ouverts en section 9 restent à traiter.
