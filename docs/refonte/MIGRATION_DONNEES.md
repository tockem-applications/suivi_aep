# Migration des données — v1 (MySQL) → v2 (SQLite / PostgreSQL)

> Document technique d'accompagnement du `CAHIER_DES_CHARGES.md` (exigences **EX-700 à EX-705**).
> Il décrit le **schéma source v1**, le **schéma cible v2**, le **mapping table par table**,
> les **transformations** (nettoyage, sécurité, recalculs) et la **stratégie d'exécution**.
>
> Base source de référence : schéma **version 10** (`donnees/bd/suivi_aep_fokoue (10).sql`,
> `migration_9_to_10.sql`, `create_borne_fontaine_tables.sql`, `create_tarif_differencie.sql`,
> `create_vue_abones_facturation.sql`, `update_database_9_to_10.php`).

---

## 1. Principes de migration

- **MIG-1** : Migration **complète** de toutes les tables métier v1 (EX-701).
- **MIG-2** : Outil dédié (`cmd/migrate`) qui lit la base MySQL v1 (connexion en lecture seule) et
  écrit dans la base v2 (SQLite ou PostgreSQL selon la cible).
- **MIG-3** : Migration **idempotente** (rejouable) et avec **exécution à blanc** (`--dry-run`) produisant un rapport.
- **MIG-4** : **Rapport de contrôle** : nombre de lignes source vs cible par table, écarts, erreurs, et
  contrôle des **totaux financiers** (recouvrements, impayés, versements) — voir §6.
- **MIG-5** : Aucune reprise de secret : mots de passe et jetons v1 **non repris tels quels** (voir §5).
- **MIG-6** : Les **vues SQL** v1 (`vue_abones_facturation`, `vue_indexes_tarifs_resolved`) ne sont **pas migrées
  comme données** : la logique de calcul est réimplémentée dans le moteur métier Go (voir §7).
- **MIG-7** : Normalisation d'encodage : les tables v1 sont en `latin1`/`utf8` mélangés → **tout convertir en UTF-8**
  (détecter et corriger le mojibake éventuel sur les accents).

---

## 2. Cartographie des tables source v1

Tables présentes en v1 (version 10), regroupées par domaine :

| Domaine | Tables v1 |
|---------|-----------|
| Structure | `aep`, `reseau`, `abone`, `compteur`, `compteur_abone`, `compteur_reseau`, `compteur_aep`, `position_compteur_aep`, `borne_fontaine`, `bf_gerant`, `branchement_abonne` |
| Tarification | `constante_reseau`, `tarif_differencie` |
| Cycle mensuel | `mois_facturation`, `indexes`, `facture`, `impaye` |
| Finances | `flux_financier`, `categorie_flux_manuel`, `config_compte_rendu_financier`, `redevance`, `versements` |
| Interventions | `interventions`, `intervention_rh`, `intervention_rm`, `ressources_humaines`, `ressources_materielles` |
| Sécurité / accès | `users`, `utilisateur` (legacy), `roles`, `user_roles`, `page_role_aep`, `pages`, `clefs`, `user_clefs`, `travail`, `logs` |
| Vues (non migrées) | `vue_abones_facturation`, `vue_indexes_tarifs`, `vue_indexes_tarifs_resolved` |

**Décisions de nettoyage** :

- **`utilisateur`** (InnoDB, sans mot de passe) est une table **legacy** doublon de `users` → **ignorée**
  (les comptes réels sont dans `users`).
- **`travail`** (liaison AEP ↔ utilisateur) → devient l'affectation **utilisateur ↔ AEP** en v2 (isolation multi-AEP, EX-242).
- **`pages`** / **`page_role_aep`** (permissions « par page » auto-enregistrées) → **remplacées** par le modèle
  RBAC ressource/action de la v2 (EX-241). On ne migre pas la matrice page-par-page telle quelle : on la
  **traduit** en permissions (voir §4).
- **`position_compteur_aep`** / **`compteur_aep`** → conservés (position production/distribution/réservoir des compteurs d'AEP).

---

## 3. Mapping détaillé table par table (données métier)

Légende : `→` conservé tel quel · `⇒` transformé · `∅` non migré.

### 3.1 Structure

| v1 (table.colonne) | v2 (entité.champ) | Transformation |
|--------------------|-------------------|----------------|
| `aep.id` | `aep.id` | → (conserver les IDs pour préserver les FK) |
| `aep.libele` | `aep.libelle` | ⇒ correction accents/UTF-8 |
| `aep.description` | `aep.description` | → |
| `aep.fichier_facture` | `aep.modele_facture` | ⇒ mapper `model_fokoue`→`fokoue`, `model_nkongzem`→`nkongzem` |
| `aep.date` | `aep.date_creation` | → |
| `aep.numero_compte`, `aep.nom_banque` | `aep.compte_bancaire`, `aep.banque` | → |
| *(type distribution RDS/RDC en v1 : déduit du code)* | `aep.type_distribution` | ⇒ valeur par défaut `RDS`, à confirmer par AEP |
| `reseau.*` (`id`, `nom`, `abreviation`, `date_creation`, `description_reseau`, `id_aep`) | `reseau.*` | → ; ajouter `id_reseau_parent` (v1 gère la hiérarchie via code, à recalculer si applicable) |
| `abone.id, nom, numero_telephone, numero_compte_anticipation, etat, rang, id_reseau` | `abonne.*` | → ; `etat` (`actif`/`inactif`) ⇒ booléen `est_actif` |
| `abone.type_abone`, `abone.type_abonnement` | `abonne.type` (`BP`/`BF`) | ⇒ fusionner les deux colonnes redondantes en une |
| `abone.tarif_differencie_autorise` | `abonne.tarif_differencie_autorise` | → (booléen) |
| `compteur.*` (`numero_compteur`, `longitude`, `latitude`, `derniers_index`, `description`) | `compteur.*` | → |
| `compteur_abone` (N-N) | lien `abonne.id_compteur` ou table `compteur_abonne` | → (garder la relation) |
| `compteur_reseau`, `compteur_aep`, `position_compteur_aep` | idem v2 | → (types production/distribution/réservoir via position) |
| `borne_fontaine.*` (`id_abone`, `numero_borne`, `localisation`, dates, `description`) | `borne_fontaine.*` | → |
| `bf_gerant.*` (nom, tél, pièce identité, `date_debut`, `date_fin`, `est_actif`, notes) | `bf_gerant.*` | → |
| `branchement_abonne.*` (`id_abone`, `quartier`, `code_abonne`, `telephone`, `statut`, `mois`, `versement_fcfa`, `created_at`) | `branchement.*` | → ; `statut` normalisé (`OK`/`en_attente`) |

### 3.2 Tarification

| v1 | v2 | Transformation |
|----|----|----------------|
| `constante_reseau.*` (`prix_metre_cube_eau`, `prix_entretient_compteur`, `prix_tva`, `date_creation`, `est_actif`, `id_aep`) | `tarif.*` | ⇒ corriger la faute `entretient`→`entretien` dans les noms de champs v2 |
| `tarif_differencie.*` (`id_constante_reseau`, prix, `min_consommation`, `max_consommation`, `date_creation`) | `tarif_differencie.*` | → |

### 3.3 Cycle mensuel (relevés, facturation, recouvrement)

| v1 | v2 | Transformation |
|----|----|----------------|
| `mois_facturation.*` (`mois`, `date_facturation`, `date_depot`, `date_releve`, `id_constante`, `est_actif`, `description`) | `mois_facturation.*` | → ; ajouter `est_mois_base` (booléen) déduit de la logique v1 (mois socle) |
| `indexes.*` (`id_compteur`, `id_mois_facturation`, `ancien_index`, `nouvel_index`, `message`, `id_tarif_differencie`) | `releve.*` | → |
| `facture.*` (`id_indexes`, `montant_verse`, `date_paiement`, `penalite`, `id_abone`, `message`) | `facture.*` | → ; les montants **calculés** (consommation, TVA, total, restant) **ne sont pas stockés** en v1 (ils viennent de la vue) → recalculés en v2 (§7) |
| `impaye.*` (`montant`, `est_regle`, `id_facture`, `date_reglement`) | `impaye.*` | → ; `est_regle` (0/1) ⇒ booléen |

### 3.4 Finances

| v1 | v2 | Transformation |
|----|----|----------------|
| `flux_financier.*` (`date`, `mois`, `libele`, `prix`, `type`, `description`, `id_aep`) | `flux_financier.*` | → ; `type` (`entree`/`sortie`) normalisé ; rattacher à une catégorie si déductible |
| `categorie_flux_manuel.*` (`nom`, `type_flux`, `description`, `id_aep`, `est_actif`, + `code_budgetaire`, `ordre`, `activite` ajoutés par scripts ultérieurs) | `categorie_flux.*` | → ; vérifier la présence effective des colonnes `code_budgetaire`/`ordre`/`activite` dans la base cliente avant migration |
| `config_compte_rendu_financier.*` (`code_type`, `libelle`, `type_flux`, `id_aep`, `code_budgetaire`, `activite_associee`) | `config_compte_rendu.*` | → |
| `redevance.*` (`libele`, `pourcentage`, `type`, `mois_debut`, `base_calcul`, `type_calcul`, `montant_par_m3`, `est_sortie`, `id_aep`) | `redevance.*` | → |
| `versements.*` (`montant`, `date_versement`, `id_mois_facturation` [nullable], `id_redevance`, `est_valide`) | `versement_redevance.*` | → ; `est_valide` ⇒ booléen |

### 3.5 Interventions & ressources

| v1 | v2 | Transformation |
|----|----|----------------|
| `interventions.*` (`aep_id`, `titre`, `type`, `description`, `localisation`, dates prévues/réelles, `statut` enum, coûts, `created_by`, timestamps) | `intervention.*` | → (déjà propre, UTF-8) |
| `intervention_rh` (N-N + heures/coûts) | `intervention_rh` | → |
| `intervention_rm` (N-N + quantités/coûts) | `intervention_rm` | → |
| `ressources_humaines.*` (`nom`, `fonction`, `competences`, `telephone`, `statut` enum, `cout_horaire`, `actif`) | `ressource_humaine.*` | → |
| `ressources_materielles.*` (`libelle`, `categorie`, `reference`, quantités, `unite`, `cout_unitaire`, `statut` enum, `actif`) | `ressource_materielle.*` | → |

### 3.6 Sécurité / accès

| v1 | v2 | Transformation |
|----|----|----------------|
| `users.*` (`email`, `nom`, `prenom`, `numero_telephone`, `password` SHA-256, `salt`) | `utilisateur.*` | ⇒ profil migré ; **mot de passe NON repris** → forcer réinitialisation au 1er login (§5) |
| `utilisateur` (legacy) | ∅ | non migré (doublon) |
| `roles.*` (Administrateur, Releveur, Comptable, Recouvreur, Visiteur, `all`) | `role.*` | ⇒ reprendre les rôles métier ; `all` fusionné dans Administrateur |
| `user_roles` (N-N) | `utilisateur_role` | → |
| `travail` (AEP ↔ utilisateur) | `utilisateur_aep` | ⇒ devient l'autorisation d'accès par AEP (EX-242) |
| `page_role_aep` + `pages` | permissions RBAC | ⇒ **traduites** en permissions ressource/action (§4), non copiées telles quelles |
| `clefs`, `user_clefs` | `cle_inscription`, lien | → |
| `logs.*` (`user_id`, `page_libelle`, `action`, `timestamp`) | `journal_audit.*` | ⇒ importés comme historique d'audit (best effort) |

---

## 4. Traduction des permissions (pages → RBAC)

La v1 gère les droits via `page_role_aep(page_id, role_id, write_access)` où les « pages » sont
auto-enregistrées. La v2 utilise un **RBAC ressource × action** (EX-241). Règle de traduction :

- Chaque « page » v1 est rattachée à une **ressource** v2 (ex. `?page=abonne` → ressource `abonne`).
- `write_access = 0` → permission `read` sur la ressource.
- `write_access = 1` → permissions `read, create, update, delete` sur la ressource.
- Les routes d'exécution sensibles (backup, mise à jour, migration) → permission `execute`, réservée Administrateur.
- Fournir une **table de correspondance page→ressource** (`docs/refonte/mapping_pages_ressources.csv`) à compléter
  et à valider ; en l'absence de correspondance, la page est rattachée par défaut à Administrateur uniquement (fail-safe).
- Les rôles standard reçoivent un **jeu de permissions par défaut** (à valider avec le client) plutôt qu'une
  copie de la matrice v1, souvent incohérente.

---

## 5. Transformations de sécurité

- **SEC-MIG-1** : Les mots de passe v1 (`hash('sha256', password.salt)`) sont **cryptographiquement faibles** et ne
  peuvent pas être « re-hachés » en argon2 sans le mot de passe clair. → Stratégie : **invalider** les mots de passe,
  créer les comptes en état « réinitialisation requise », envoyer un lien/procédure de définition d'un nouveau mot de passe
  (argon2id, EX-220). Un compte admin initial est créé lors de l'assistant de premier démarrage.
- **SEC-MIG-2** : Aucune reprise des jetons/`clefs` sensibles en clair sans nécessité ; régénérer les clés d'inscription.
- **SEC-MIG-3** : Nettoyage des données : supprimer les éventuels résidus de debug, valider les types (montants, dates),
  rejeter/signaler les lignes incohérentes dans le rapport.
- **SEC-MIG-4** : La migration s'exécute avec un compte MySQL **en lecture seule** sur la source.

---

## 6. Rapport de migration et contrôles (EX-703, EX-705)

Le rapport (`migration-report.json` + résumé lisible) contient au minimum :

- Par table : `lignes_source`, `lignes_migrees`, `lignes_ignorees`, `lignes_en_erreur`.
- **Contrôles de cohérence financière** (comparaison v1 recalculé ↔ v2) :
  - Somme des `montant_verse` (recouvrements) par AEP et par mois.
  - Somme des impayés / `montant_restant` par AEP et par mois.
  - Somme des versements de redevances.
  - Nombre de factures par mois de facturation.
- **Contrôles d'intégrité référentielle** : aucune FK orpheline en cible.
- Liste des **anomalies** (mojibake corrigé, dates invalides, `etat`/`statut` inconnus, doublons).
- Statut global : `succès` / `succès avec avertissements` / `échec`.

Critère d'acceptation (EX-705) : les totaux financiers v2 doivent être **égaux** aux totaux v1 recalculés
(tolérance 0 sur les entiers FCFA, arrondi documenté pour la TVA).

---

## 7. Recalcul des montants (vue de facturation)

La v1 ne stocke pas les montants facturés : ils sont calculés par la vue `vue_abones_facturation`
(via `vue_indexes_tarifs_resolved` pour le tarif applicable). En v2, cette logique est **réimplémentée
dans le moteur métier Go** (testée unitairement, EX-807) selon la formule :

```
consommation           = nouvel_index - ancien_index
prix applicable        = tarif différencié si abonné autorisé ET palier correspondant,
                         sinon tarif de base (constante_reseau)
montant_conso          = consommation * prix_metre_cube_eau
montant_conso_entretien= montant_conso + prix_entretien_compteur
montant_conso_tva      = montant_conso_entretien * (1 + prix_tva/100)
montant_total          = montant_conso_tva + penalite
montant_restant        = montant_total - montant_verse   (= impaye)
montant_a_valider      = min(montant_total, montant_verse)
```

Règle du tarif différencié (reprise de `vue_indexes_tarifs_resolved`) :
1. si `indexes.id_tarif_differencie` est renseigné → utiliser ce tarif (tarif « stocké » au moment de la facturation) ;
2. sinon, chercher le palier `tarif_differencie` dont `min_consommation ≤ consommation < max_consommation`
   (palier au `min_consommation` le plus élevé qui matche) ;
3. sinon → tarif de base de la `constante_reseau` ;
4. si `abonne.tarif_differencie_autorise = 0` → **toujours** le tarif de base.

**Important** : pour préserver l'historique, la migration **fige** le tarif appliqué de chaque facture
(en renseignant l'équivalent de `id_tarif_differencie` ou en stockant les prix unitaires sur la facture v2),
afin qu'un changement de tarif futur ne modifie pas les factures passées.

---

## 8. Ordre d'exécution (dépendances FK)

1. `aep`
2. `reseau` (dépend de `aep`)
3. `compteur`, `position_compteur_aep`
4. `abonne` (dépend de `reseau`), puis `compteur_abone`, `compteur_reseau`, `compteur_aep`
5. `borne_fontaine` (dépend de `abone`), `bf_gerant`
6. `branchement_abonne`
7. `constante_reseau` (dépend de `aep`), `tarif_differencie`
8. `mois_facturation` (dépend de `constante_reseau`)
9. `indexes` (dépend de `compteur`, `mois_facturation`, `tarif_differencie`)
10. `facture` (dépend de `indexes`, `abone`), `impaye`
11. `categorie_flux_manuel`, `config_compte_rendu_financier`, `flux_financier`
12. `redevance`, `versements`
13. `ressources_humaines`, `ressources_materielles`, `interventions`, `intervention_rh`, `intervention_rm`
14. `roles`, `users`, `user_roles`, `travail` (→ `utilisateur_aep`), `clefs`, `user_clefs`
15. permissions RBAC (traduction §4), `logs` (→ journal d'audit)

Chaque étape en **transaction** ; en cas d'échec, rollback de l'étape et report dans le rapport.

---

## 9. Procédure opérationnelle

```bash
# 1. Sauvegarde de la base v1 (sécurité)
mysqldump --single-transaction suivi_aep_fokoue > backup_v1.sql

# 2. Exécution à blanc (aucune écriture) — produit le rapport
migrate --source "mysql://readonly:***@host/suivi_aep_fokoue" \
        --target "sqlite:///data/tockem.db" \
        --dry-run --report migration-report.json

# 3. Vérifier le rapport (totaux, anomalies), corriger si besoin

# 4. Migration réelle
migrate --source "mysql://readonly:***@host/suivi_aep_fokoue" \
        --target "postgres://user:***@host/tockem" \
        --report migration-report.json

# 5. Contrôles post-migration + premier démarrage (assistant : admin, licence, charte)
```

- La cible (`--target`) est **SQLite** (déploiement local) ou **PostgreSQL** (en ligne), même code (EX-143).
- La migration est rejouable (MIG-3) : elle vide/upsert la cible de façon déterministe (stratégie à documenter :
  table cible vierge recommandée pour la première mise en service).

---

## 10. Points à valider avec le client avant migration

1. **`type_distribution` (RDS/RDC)** par AEP : non stocké explicitement en v1, à confirmer.
2. **Hiérarchie des réseaux** (`id_reseau_parent`) : à reconstruire si la v1 l'implémente par convention de nommage.
3. **Jeu de permissions par défaut** des rôles v2 (plutôt que copie de la matrice v1).
4. **Présence effective** des colonnes tardives (`code_budgetaire`, `ordre`, `activite` sur `categorie_flux_manuel`)
   dans la base réellement déployée chez le client.
5. **Mois de base** (`est_mois_base`) : identifier les mois socles historiques.
6. Politique de **réinitialisation des mots de passe** (procédure de contact des utilisateurs).

---

*Document de référence pour l'outil `cmd/migrate`. Toute divergence de schéma constatée sur la base réelle
du client doit être ajoutée ici et gérée par l'outil (colonnes optionnelles détectées dynamiquement).*
