# Cycle mensuel de facturation (aperçu)

Schéma de référence pour la documentation détaillée — **à compléter avec captures d'écran**.

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│ 1. Relevés   │ ──► │ 2. Facturer │ ──► │ 3. Imprimer │ ──► │ 4. Recouvrer│
│   (index)   │     │   le mois   │     │  factures   │     │  versements │
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
       │                    │                    │                    │
       └────────────────────┴────────────────────┴────────────────────┘
                                    │
                                    ▼
                          ┌─────────────────┐
                          │ 5. Reporting    │
                          │ (dashboard,     │
                          │  compte expl.)  │
                          └─────────────────┘
```

## Ordre recommandé

1. **Mois de facturation** — créer ou activer le mois (attention au *mois de base* si applicable).
2. **Relèves** — saisir ancien / nouvel index pour chaque compteur.
3. **Facturation** — générer les factures du mois.
4. **Impression** — distribuer les factures aux abonnés.
5. **Recouvrement** — enregistrer les paiements au fur et à mesure.
6. **Pénalités** — si applicable, après échéance.
7. **Tableau de bord / compte d'exploitation** — contrôle et clôture.

## Fiches détaillées

Voir les chapitres `03-facturation/` et `06-tableau-de-bord/`.
