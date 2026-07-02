# Plan de la documentation utilisateur

Document de travail pour construire progressivement le guide utilisateur de **Tockem SPE**.

---

## 1. Objectifs

- Permettre à un nouvel utilisateur de **prendre en main l'application** sans formation longue.
- Documenter les **parcours métier complets** (de la création d'un abonné jusqu'au recouvrement et au compte d'exploitation).
- Réduire les appels support sur les sujets récurrents : licence, sélection AEP, facturation, mois de base, sauvegarde.
- Séparer clairement **guide utilisateur** (ce dossier) et **documentation technique** (`DOCKER.md`, migrations BDD, etc.).

---

## 2. Principes de rédaction

Chaque fiche fonctionnalité suit le **même modèle** :

```markdown
# Titre de la fonctionnalité

## À quoi ça sert ?
(2–3 phrases, bénéfice métier)

## Qui peut y accéder ?
(Rôle / menu requis)

## Accès dans l'application
Menu → Sous-menu → URL ou chemin

## Procédure pas à pas
1. ...
2. ...

## Points d'attention
- Erreurs fréquentes
- Règles métier (ex. mois de base, RDS/RDC)

## Voir aussi
- Liens vers autres chapitres
```

**Règles :**
- Tutoiement ou vouvoiement : **vouvoiement** (contexte professionnel mairie / régie).
- Captures d'écran : PNG, nom `menu-fonction-etape.png`.
- Pas de jargon technique (PHP, SQL, Docker) dans ce guide.
- Prioriser les **procédures** plutôt que la description des écrans.

---

## 3. Structure des chapitres

### 00 — Introduction
| Fichier | Contenu |
|---------|---------|
| `00-introduction/presentation.md` | Qu'est-ce que Tockem SPE, pour quelles missions |
| `00-introduction/glossaire.md` | AEP, BP, BF, RDS, RDC, mois de base, index, recouvrement… |
| `00-introduction/prerequis.md` | Navigateur, connexion, droits minimaux |

### 01 — Premiers pas
| Fichier | Contenu | Menu app |
|---------|---------|----------|
| `01-premiers-pas/connexion.md` | Se connecter, mot de passe oublié | Connexion |
| `01-premiers-pas/licence.md` | Importer / renouveler la licence | Administration → Licence |
| `01-premiers-pas/selection-aep.md` | Choisir un AEP, « tout fermer » | Accueil / liste AEP |
| `01-premiers-pas/navigation.md` | Barre de menus, recherche, nom AEP cliquable | Général |
| `01-premiers-pas/profil-utilisateur.md` | Déconnexion, rôles visibles | — |

### 02 — Structure du réseau
| Fichier | Contenu | Menu app |
|---------|---------|----------|
| `02-structure/aep.md` | Créer, modifier un AEP (banque, modèle facture, RDS/RDC) | Structure → AEPs |
| `02-structure/reseaux.md` | Réseaux, hiérarchie, détail | Structure → Réseaux |
| `02-structure/abonnes.md` | Branchements privés (BP), compteurs, fiche abonné | Structure → Abonnés |
| `02-structure/bornes-fontaines.md` | BF, gérants | Structure → Bornes Fontaines |
| `02-structure/tarifs-reseau.md` | Constantes, prix m³, TVA, entretien compteur | Finances → Tarifs |
| `02-structure/import-fokoue-data.md` | Import de données (si utilisé) | Structure → Fokoue data |

### 03 — Facturation & recouvrement
| Fichier | Contenu | Menu app |
|---------|---------|----------|
| `03-facturation/mois-facturation.md` | Créer un mois, mois actif, mois de base | Finances → Mois de Recouvrement |
| `03-facturation/releves.md` | Saisie des index, relevés | Facturation → Relèves |
| `03-facturation/facturation.md` | Lancer la facturation d'un mois | Facturation → Mois Facturés |
| `03-facturation/impression-factures.md` | Modèles Fokoué / Nkongzem, impression | Liste factures |
| `03-facturation/recouvrement.md` | Saisie versements, filtres insolvables… | Facturation → Recouvrement |
| `03-facturation/recouvrement-v2.md` | Version V2 (si conservée) | Recouvrement V2 |
| `03-facturation/penalites.md` | Pénalités, application / annulation | Facturation → Pénalités |
| `03-facturation/statistiques-reseau.md` | Vue statistiques par réseau | Facturation → Statistiques |

### 04 — Finances & reporting
| Fichier | Contenu | Menu app |
|---------|---------|----------|
| `04-finances/transactions.md` | Entrées / sorties manuelles | Finances → Entrée/Sortie |
| `04-finances/redevances.md` | Création, calcul, versements | Finances → Redevances |
| `04-finances/branchements.md` | Suivi branchements et versements | Finances → Branchements |
| `04-finances/compte-exploitation.md` | Grille compte d'exploitation | Compte d'exploitation |
| `04-finances/synthese-compte-exploitation.md` | Synthèse annuelle / période | Synthèse |
| `04-finances/analyse-financiere.md` | Graphiques et indicateurs | Analyse Financière |
| `04-finances/config-compte-rendu.md` | Libellés recouvrements, branchements… | Config. Compte Rendu |
| `04-finances/categories-flux-manuels.md` | Catégories de dépenses manuelles | Catégories Flux Manuels |

### 05 — Administration
| Fichier | Contenu | Menu app |
|---------|---------|----------|
| `05-administration/utilisateurs.md` | Créer un compte | Administration → Enregistrement |
| `05-administration/roles.md` | Rôles et droits par page | Gestion des rôles |
| `05-administration/clefs.md` | Clés d'inscription | Gestion des clefs |
| `05-administration/sauvegarde.md` | Export / import base | Sauvegarde & Restauration |
| `05-administration/a-propos.md` | Version, licence affichée | À propos |

> **Note :** La migration BDD 9→10 reste dans la doc **technique**, pas utilisateur.

### 06 — Tableau de bord
| Fichier | Contenu | Menu app |
|---------|---------|----------|
| `06-tableau-de-bord/vue-ensemble.md` | KPIs, période (3/6/12 mois) | Clic sur nom AEP |
| `06-tableau-de-bord/graphiques.md` | Consommation, recouvrement, rendement |
| `06-tableau-de-bord/bf-bp.md` | Tableau facturation BF / BP |

### 07 — FAQ & dépannage
| Fichier | Contenu |
|---------|---------|
| `07-faq-depannage/faq.md` | Questions fréquentes |
| `07-faq-depannage/erreurs-courantes.md` | Licence, AEP non sélectionné, accès refusé |
| `07-faq-depannage/bonnes-pratiques.md` | Sauvegardes, clôture mensuelle, ordre des opérations |

### Annexes
| Fichier | Contenu |
|---------|---------|
| `annexes/cycle-mensuel.md` | Schéma : relevé → facturation → recouvrement → clôture |
| `annexes/fiches-roles.md` | Matrice rôle × fonctionnalité (à compléter) |
| `annexes/contacts-support.md` | Support Tockem / AMGEEA |

---

## 4. Parcours métier à documenter en priorité

Ces **scénarios bout-en-bout** guident la rédaction des premières fiches :

### Parcours A — Nouveau mois de facturation (priorité 1)
1. Sélectionner l'AEP  
2. Vérifier / créer le mois de facturation  
3. Saisir les relevés  
4. Lancer la facturation  
5. Imprimer les factures  
6. Saisir les recouvrements  
7. Consulter le tableau de bord  

→ Alimenter les chapitres **01, 03, 06**.

### Parcours B — Nouvel abonné (priorité 2)
1. Créer le réseau si besoin  
2. Créer l'abonné et le compteur  
3. Définir tarif / constantes  
4. Premier relevé au mois suivant  

→ Alimenter **02-structure**.

### Parcours C — Fin de période / reporting (priorité 3)
1. Vérifier recouvrements et pénalités  
2. Saisir flux manuels et redevances  
3. Générer compte d'exploitation et synthèse  

→ Alimenter **04-finances**.

### Parcours D — Mise en service (priorité 4)
1. Installation (référence doc technique séparée)  
2. Licence  
3. Premier utilisateur admin  
4. Création AEP  
5. Première sauvegarde  

→ Alimenter **01, 05**.

---

## 5. Plan de réalisation par phases

| Phase | Durée estimée | Livrables |
|-------|---------------|-----------|
| **Phase 1** | 1 semaine | Glossaire, premiers pas, parcours A (fiches facturation) |
| **Phase 2** | 1 semaine | Structure (AEP, abonnés, réseaux), parcours B |
| **Phase 3** | 1 semaine | Finances + tableau de bord |
| **Phase 4** | 3–5 jours | Administration, FAQ, annexes cycle mensuel |
| **Phase 5** | Optionnel | PDF unique, aide intégrée dans l'app (`?page=aide`) |

---

## 6. Formats de publication (plus tard)

| Format | Usage |
|--------|--------|
| **Markdown** (ce dossier) | Source, versionnement Git |
| **PDF** | Distribution clients sans accès au code |
| **Aide in-app** | Liens depuis `?page=aide` ou icône `?` sur les écrans complexes |
| **Vidéos courtes** | Recouvrement, relevés (optionnel) |

Outils possibles pour PDF : Pandoc, MkDocs, ou export depuis un éditeur.

---

## 7. Prochaines actions recommandées

1. **Valider** ce plan (ajouter / retirer des sections selon vos clients).
2. **Rédiger en priorité** le glossaire et le parcours A (mois type).
3. **Capturer des écrans** sur une base de démo (AEP Fokoué).
4. **Faire relire** par un agent de recouvrement et un comptable.
5. **Itérer** : une fiche par PR ou par sprint.

---

## 8. Fichiers à créer en premier (ordre suggéré)

1. `00-introduction/glossaire.md`
2. `01-premiers-pas/connexion.md`
3. `01-premiers-pas/selection-aep.md`
4. `03-facturation/releves.md`
5. `03-facturation/facturation.md`
6. `03-facturation/recouvrement.md`
7. `annexes/cycle-mensuel.md`
8. `07-faq-depannage/faq.md`

---

*Dernière mise à jour : plan initial — contenu des chapitres à rédiger.*
