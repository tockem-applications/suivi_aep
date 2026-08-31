# Documentation utilisateur — sources LaTeX

Version LaTeX de la documentation utilisateur **Tockem SPE**, compilable avec **pdfLaTeX**.

## Prérequis

- [MiKTeX](https://miktex.org/) ou [TeX Live](https://www.tug.org/texlive/)
- La commande `pdflatex` doit être disponible dans le `PATH`

## Structure

```
latex/
├── main.tex              # Document principal
├── preambule.tex         # Packages et commandes (français, hyperliens, figures)
├── build.bat             # Script de compilation Windows
├── chapitres/
│   ├── 00-page-titre.tex
│   ├── 01-introduction.tex
│   ├── 02-installation.tex
│   ├── 03-fonctionnalites.tex
│   ├── 04-aide.tex
│   └── 05-conclusion.tex
└── images/               # Captures d'écran (voir liste ci-dessous)
```

## Compiler

Depuis ce dossier :

```bat
build.bat
```

Ou manuellement :

```bat
pdflatex main.tex
pdflatex main.tex
```

Le PDF est produit sous `main.pdf` et copié vers `../doc_utilisateur_web_app.pdf`.

## Captures d'écran

Placer les images PNG dans `images/`. Tant qu'un fichier est absent, un cadre placeholder s'affiche à la compilation.

| Fichier | Contenu |
|---------|---------|
| `fig01-creer-aep.png` | Formulaire de création d'AEP |
| `fig02-tableau-de-bord.png` | Tableau de bord AEP |
| `fig03-tarifs-reseau.png` | Tarifs réseau |
| `fig04-page-reseaux.png` | Page des réseaux |
| `fig05-creation-reseau.png` | Création de sous-réseau |
| `fig06-ajouter-abonne.png` | Ajouter un abonné |
| `fig07-liste-abonnes.png` | Liste des abonnés |
| `fig08-info-abonne.png` | Fiche abonné |
| `fig09-page-releves.png` | Page des relèves |
| `fig10-importer-index.png` | Import des index |
| `fig11-formulaire-facturation.png` | Formulaire de facturation |
| `fig12-facture-generee.png` | Facture générée |
| `fig13-page-recouvrement.png` | Page recouvrement |
| `fig14-completer-versement.png` | Compléter un versement |
| `fig15-options-recouvrement.png` | Filtres recouvrement |

Les figures peuvent être extraites de l'ancien PDF `doc_utilisateur_web_app.pdf` ou recapturées depuis l'application.

## Modifier le contenu

Éditer les fichiers dans `chapitres/`, puis relancer `build.bat`. Le vouvoiement et la structure reprennent la documentation de juillet 2025.
