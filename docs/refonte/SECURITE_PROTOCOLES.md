# Protocoles de vérification de sécurité — Tockem SPE v2

> Document d'accompagnement du `CAHIER_DES_CHARGES.md` (exigences **EX-200 à EX-283**).
> Il définit les **règles de sécurité applicables à chaque endpoint**, les **checklists de revue**,
> la **configuration durcie**, et le **plan de tests de sécurité**.
> Objectif : garantir qu'aucune des failles de la v1 ne puisse réapparaître (critère **AC-1**).

---

## 1. Modèle de menace (résumé)

| Actif | Menace | Contre-mesure (réf. CDC) |
|-------|--------|--------------------------|
| Données financières (factures, recouvrements) | Altération, vol, injection SQL | EX-200/201, RBAC EX-240, audit EX-270 |
| Comptes utilisateurs | Bruteforce, vol de session, élévation de privilège | EX-220/221/222/223, RBAC EX-240 |
| Base de données / dumps | Exfiltration (fichiers exposés en HTTP en v1) | EX-261, EX-330, backups protégés |
| Code métier | Reverse engineering, modification chez le client | Binaire Go compilé EX-102/330 |
| Licence | Contournement, copie | EX-400 à EX-407 |
| Serveur | RCE via upload, exécution de fichier | EX-250/251/252 |
| Intégrité applicative | Mise à jour malveillante | Paquet signé EX-320/324 |

Profils d'attaquant considérés : anonyme externe, utilisateur authentifié à faibles droits (ex. Visiteur),
utilisateur légitime tentant d'accéder à un AEP non autorisé, poste client cherchant à lire/modifier le code.

---

## 2. Protocole standard par endpoint (à appliquer SANS exception)

Chaque route de l'API passe par la **chaîne de middlewares** suivante, dans cet ordre :

```
1. Rate limiting / anti-abus
2. Sécurité HTTP (headers, CORS)
3. Authentification (session/JWT valide)      → 401 si absent/expiré
4. CSRF (si méthode mutante)                  → 403 si token invalide
5. Autorisation RBAC (ressource + action)     → 403 si permission manquante
6. Isolation AEP (l'utilisateur a accès à cet AEP) → 403 sinon
7. Validation d'entrée (schéma typé)          → 422 si invalide
8. Handler métier (transaction si écriture)
9. Journalisation d'audit (si action sensible)
10. Réponse JSON (jamais de détail d'erreur interne)
```

### 2.1 Fiche de contrôle par endpoint

Pour **chaque** endpoint, documenter (tableau `docs/refonte/endpoints_securite.csv`) :

| Colonne | Valeurs |
|---------|---------|
| Méthode + chemin | `GET /api/abonnes`, `POST /api/factures`… |
| Auth requise | oui / non (rares cas publics : login, healthcheck) |
| Permission | ressource + action (`abonne:read`, `facture:create`, `backup:execute`…) |
| Isolation AEP | oui / non |
| CSRF | requis si POST/PUT/PATCH/DELETE |
| Schéma d'entrée | référence du DTO/Zod |
| Effets | lecture / écriture / exécution |
| Audité | oui / non (toute écriture sensible = oui) |
| Limite de débit | seuil spécifique si besoin (ex. login) |

Règle : **aucun endpoint ne peut être fusionné en production sans sa ligne dans ce tableau** (revue obligatoire).

---

## 3. Règles par classe de vulnérabilité (correspondance failles v1)

### 3.1 Injection SQL (v1 : `Abones.php`, `mois_facturation.php`)

- **P-SQL-1** : Toute requête utilise des **paramètres liés** (sqlc/requêtes préparées). Interdiction absolue de
  concaténer une valeur dans une chaîne SQL.
- **P-SQL-2** : Les identifiants dynamiques (colonne de tri, sens, table) proviennent d'une **liste blanche** en dur.
- **P-SQL-3** : Aucune API type `query(string)` exposée dans la couche `store`.
- **Vérification** : revue de code + règle `gosec` (G201/G202) en CI + test d'injection sur les endpoints de recherche/tri.

### 3.2 XSS (v1 : `message.php`, `index.php`)

- **P-XSS-1** : Rendu React (échappement par défaut) ; `dangerouslySetInnerHTML` interdit sauf contenu passé par DOMPurify.
- **P-XSS-2** : L'API ne renvoie que du **JSON** ; aucune donnée utilisateur reflétée dans du HTML serveur.
- **P-XSS-3** : **CSP stricte** (voir §4) sans `unsafe-inline` en production.
- **Vérification** : test e2e injectant des charges XSS dans les champs texte (nom abonné, description) + contrôle CSP.

### 3.3 Authentification & sessions (v1 : SHA-256, pas de régénération)

- **P-AUTH-1** : Mots de passe en **argon2id** (paramètres documentés) ; jamais de hash rapide.
- **P-AUTH-2** : Politique : longueur ≥ 12, refus des mots de passe courants, verrouillage temporaire après N échecs (ex. 5) + back-off.
- **P-AUTH-3** : Session régénérée à la connexion et au changement de privilège ; détruite à la déconnexion ; expiration d'inactivité.
- **P-AUTH-4** : Cookies `HttpOnly`, `Secure` (en ligne), `SameSite=Strict`.
- **P-AUTH-5** : 2FA TOTP obligatoire pour les rôles Administrateur (recommandé) ; re-authentification avant actions critiques (§3.7).
- **Vérification** : tests unitaires (hash, verrouillage), test de fixation de session, revue des flags cookie.

### 3.4 CSRF (v1 : absent sur la plupart des actions)

- **P-CSRF-1** : Protection CSRF sur **toutes** les méthodes mutantes (double-submit token ou en-tête `X-CSRF-Token` + cookie).
- **P-CSRF-2** : Le token est lié à la session, comparé en **temps constant**.
- **Vérification** : test d'intégration rejouant une requête mutante sans/with token invalide → 403.

### 3.5 Contrôle d'accès (v1 : contournable en accès direct)

- **P-RBAC-1** : Autorisation **centralisée côté serveur**, appliquée par middleware à chaque route. L'UI ne fait que masquer.
- **P-RBAC-2** : Vérification **ressource + action** ET **isolation AEP** (un utilisateur ne lit/écrit que ses AEP autorisés).
- **P-RBAC-3** : **Deny by default** : toute route non explicitement autorisée est refusée.
- **P-RBAC-4** : Pas d'« auto-enregistrement » de droits (contrairement à la v1) ; les permissions sont définies explicitement.
- **Vérification** : matrice de tests rôle × endpoint (chaque rôle testé sur chaque action : autorisé/refusé) ; test d'accès inter-AEP.

### 3.6 Upload de fichiers (v1 : RCE via `uploadImage`)

- **P-UP-1** : Liste blanche d'**extensions** ET de **types MIME réels** (sniffing du contenu), taille max, nom **régénéré** (UUID).
- **P-UP-2** : Stockage **hors racine servie**, sans droit d'exécution ; téléchargement via endpoint contrôlé (auth + permission).
- **P-UP-3** : Imports CSV/SQL traités en **lecture seule** (parsing), jamais inclus/exécutés ; validation de structure.
- **Vérification** : test d'upload d'un `.php`/exécutable → rejeté ; test de contenu malveillant → rejeté.

### 3.7 Secrets, exposition, endpoints d'admin (v1 : `.htaccess` absent, creds en dur)

- **P-SEC-1** : **Aucun secret en dur** ; configuration via variables d'environnement / fichier non versionné / coffre.
- **P-SEC-2** : Le binaire ne sert **que** la SPA + l'API ; **pas de listing de répertoire**, pas de fichier sensible servi.
- **P-SEC-3** : `display_errors`/stack traces désactivés en prod ; erreurs génériques au client, détail en logs serveur.
- **P-SEC-4** : Endpoints **backup / restauration / mise à jour / migration** : permission `execute` (Administrateur) **+ re-authentification/2FA**, journalisés, rate-limités.
- **P-SEC-5** : Sauvegardes stockées avec permissions restreintes, jamais accessibles en HTTP.
- **Vérification** : scan des secrets (`gitleaks`), test d'accès aux chemins sensibles → 404/403, revue des messages d'erreur.

### 3.8 Journalisation & audit

- **P-AUD-1** : Journal horodaté (utilisateur, action, ressource, AEP, avant/après pour données sensibles), **append-only**, non modifiable par l'UI.
- **P-AUD-2** : Journalisation systématique des évènements de sécurité : connexions, échecs, verrouillages, changements de rôle/permission, imports, restaurations, mises à jour, opérations sur licence.
- **P-AUD-3** : Pas de secret ni de donnée personnelle superflue dans les logs.

### 3.9 Licence (renforcement v2)

- **P-LIC-1** : Vérification de **signature** (RSA/Ed25519) à chaque démarrage et périodiquement ; blocage propre si invalide/expirée (page dédiée), période de grâce configurable.
- **P-LIC-2** : **Empreinte machine** vérifiée (1 licence = 1 installation) ; heartbeat en ligne optionnel avec tolérance hors-ligne.
- **P-LIC-3** : **Aucun bypass** activable en production (le `LICENCE_DEV_MODE` v1 est réservé au dev, forcé à `0` en release).
- **P-LIC-4** : La logique de licence est dans le **binaire** (non contournable côté client) ; clé privée de signature **jamais** livrée au client.

### 3.10 Intégrité des mises à jour

- **P-UPD-1** : Les paquets de mise à jour sont **signés** ; la signature est **vérifiée avant application** (en ligne comme hors-ligne).
- **P-UPD-2** : Sauvegarde automatique + point de rollback avant toute mise à jour/migration.
- **P-UPD-3** : Le canal de mise à jour utilise TLS ; l'origine est authentifiée.

---

## 4. Configuration HTTP durcie (référence)

En-têtes appliqués à toutes les réponses (mode en ligne) :

```
Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self';
                         script-src 'self'; object-src 'none'; frame-ancestors 'none';
                         base-uri 'self'; form-action 'self'
Strict-Transport-Security: max-age=63072000; includeSubDomains; preload
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: no-referrer
Permissions-Policy: geolocation=(), microphone=(), camera=()
Cache-Control: no-store   (sur les réponses authentifiées sensibles)
```

- **CORS** : origines whitelistées explicitement ; en mode local mono-origine, CORS désactivé (même origine).
- **TLS** : obligatoire en ligne (reverse proxy Caddy/Nginx ou TLS applicatif) ; redirection HTTP→HTTPS.
- **Cookies** : `HttpOnly`, `SameSite=Strict`, `Secure` en ligne.
- **Rate limiting** : global + spécifique renforcé sur `login`, `password reset`, endpoints d'admin.

---

## 5. Validation des entrées (frontière)

- **P-VAL-1** : Double validation : **Zod** côté front (UX) et **structs validées** côté Go (autorité). Le serveur ne fait **jamais** confiance au client.
- **P-VAL-2** : Typage strict (montants entiers FCFA, dates ISO, énumérations fermées pour `type`, `statut`, `etat`).
- **P-VAL-3** : Bornes sur toutes les valeurs numériques (index ≥ 0, cohérence `nouvel_index ≥ ancien_index` signalée), longueurs de chaînes limitées.
- **P-VAL-4** : Rejet (422) avec message générique + détail de champ non sensible ; pas d'écho brut de l'entrée.

---

## 6. Plan de tests de sécurité (CI) — EX-281/282

| Type | Outil / méthode | Fréquence |
|------|-----------------|-----------|
| SAST Go | `gosec`, `govulncheck` | chaque PR |
| Lint | `golangci-lint`, `eslint` | chaque PR |
| Audit dépendances | `osv-scanner` (Go), `npm audit`/`osv` (front) | chaque PR + hebdo |
| Secrets | `gitleaks` | chaque PR |
| Tests unitaires sécurité | hash argon2, verrouillage, CSRF, tokens | chaque PR |
| Tests d'autorisation | matrice rôle × endpoint (autorisé/refusé), inter-AEP | chaque PR |
| Tests d'injection | payloads SQL/XSS sur endpoints de recherche/tri/champs libres | chaque PR |
| Tests e2e critiques | Playwright (login, facturation, recouvrement, backup) | chaque PR |
| Revue manuelle | checklist §7 sur les endpoints nouveaux/modifiés | à chaque changement d'endpoint |
| Test d'intrusion | audit externe (ou interne outillé : ZAP) | avant release majeure |

Critère de blocage de release : **0 vulnérabilité critique/élevée** non traitée, CI verte, checklist §7 complétée.

---

## 7. Checklist de revue (Definition of Done sécurité d'un endpoint)

À cocher pour tout endpoint nouveau ou modifié :

- [ ] Ligne ajoutée dans `endpoints_securite.csv` (auth, permission, AEP, CSRF, schéma, audit).
- [ ] Authentification exigée (sauf exception publique documentée).
- [ ] Permission RBAC vérifiée (ressource + action) côté serveur.
- [ ] Isolation AEP appliquée si la ressource est rattachée à un AEP.
- [ ] CSRF vérifié pour les méthodes mutantes.
- [ ] Entrée validée par schéma typé (bornes, énumérations, longueurs).
- [ ] Requêtes 100 % paramétrées ; identifiants dynamiques en liste blanche.
- [ ] Aucune donnée sensible ni détail d'erreur interne dans la réponse.
- [ ] Écritures en transaction ; opérations financières atomiques.
- [ ] Action sensible journalisée (audit).
- [ ] Uploads (le cas échéant) : type/MIME/taille/nom régénéré/hors racine.
- [ ] Tests : au moins un test « autorisé » et un test « refusé » + validation d'entrée.

---

## 8. Réponse à incident (minimum)

- Journaux d'audit et de sécurité conservés et exportables.
- Procédure de **révocation de licence** (heartbeat) et de **rotation des secrets** documentée.
- Procédure de restauration depuis sauvegarde vérifiée régulièrement.
- Point de contact éditeur (support) défini dans les paramètres de l'application.

---

*Ce document est vivant : toute nouvelle classe de risque identifiée pendant le développement y est ajoutée,
avec son protocole de vérification et son test associé.*
