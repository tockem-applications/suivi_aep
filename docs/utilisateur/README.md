# Documentation utilisateur — Tockem SPE / Suivi réseau AEP

Guide destiné aux **régisseurs**, **agents de recouvrement**, **comptables** et **administrateurs** des Adductions d'Eau Potable (AEP).

## Public cible

| Profil | Sections prioritaires |
|--------|------------------------|
| Agent terrain | Relevés, recouvrement, abonnés |
| Comptable / financier | Compte d'exploitation, redevances, transactions |
| Responsable AEP | Tableau de bord, statistiques, pénalités |
| Administrateur | Utilisateurs, rôles, licence, sauvegardes |

## Documentation PDF (LaTeX)

La version imprimable reprend la documentation de juillet 2025. Les sources sont dans **`latex/`** et se compilent avec **pdfLaTeX** :

```bat
cd docs\utilisateur\latex
build.bat
```

Voir `latex/README.md` pour la structure, la liste des captures d'écran et les commandes manuelles.

## Organisation des fichiers

```
docs/utilisateur/
├── README.md                      ← Ce fichier (index)
├── PLAN.md                        ← Plan de rédaction et priorités
├── doc_utilisateur_web_app.pdf    ← PDF généré (après compilation)
├── latex/                         ← Sources LaTeX (main.tex, chapitres/, images/)
├── 00-introduction/
├── 01-premiers-pas/
├── 02-structure/
├── 03-facturation/
├── 04-finances/
├── 05-administration/
├── 06-tableau-de-bord/
├── 07-faq-depannage/
└── annexes/
```

## État d'avancement

| Chapitre | Statut |
|----------|--------|
| Plan global | ✅ Rédigé |
| PDF LaTeX (base juillet 2025) | ✅ Sources prêtes |
| Captures d'écran dans `latex/images/` | ⏳ À ajouter |
| Fiches Markdown détaillées (`PLAN.md`) | ⏳ À rédiger |

## Comment contribuer

1. Lire `PLAN.md` pour l'ordre de rédaction recommandé.
2. Rédiger un fichier par fonctionnalité (voir modèle dans `PLAN.md`).
3. Ajouter des **captures d'écran** dans le sous-dossier `images/` du chapitre concerné.
4. Utiliser un langage simple, orienté tâches (« Comment faire X »).

## Version applicative

Documentation alignée sur l'application **Suivi réseau AEP** (menu : Structure, Facturation, Finances, Administration).
