# Cahier des charges — Reconstruction de Tockem Collect (application mobile)

> Application Android de relève d'index de compteurs sur le terrain, hors connexion,
> échangeant avec Tockem SPE par fichiers.
> Ce document couvre **l'application mobile et les modifications serveur qu'elle impose**.
> Chaque exigence est numérotée (`MOB-xxx` pour le mobile, `SRV-xxx` pour le serveur).

- **Statut** : spécification initiale, décisions cadrées avec le commanditaire
- **Version cible** : 2.0 (réécriture complète, Flutter)
- **Existant** : APK de juin 2024 en production, sources perdues ; seul subsiste un
  prototype inachevé de mai 2024 (`../Tockem_collect-dev`), qui couvre environ un quart
  du produit livré. Voir §8 pour ce qui en est repris.

---

## 1. Contexte

### 1.1 Le rôle de l'application

Le responsable de suivi exporte depuis Tockem SPE (page **Relevés**) un fichier contenant
les compteurs à relever. L'agent charge ce fichier dans l'application, parcourt le terrain
sans connexion, saisit les index, puis renvoie le fichier complété — par WhatsApp, courriel
ou copie directe. Le responsable le réimporte dans l'application web.

**Aucun appel réseau entre l'application et le serveur.** Ce choix est structurant : il rend
la relève possible dans des zones sans couverture, ce qui est le cas courant sur les AEP
concernés.

### 1.2 Ce que fait la version en production

Authentification par compte Google, import d'un fichier JSON, liste des compteurs groupés
par section et répartis sur trois onglets (tous / non relevés / relevés), saisie d'un index
par boîte de dialogue avec refus d'un index inférieur au précédent, export et envoi du
fichier, gestion de plusieurs fichiers ouverts simultanément, et une géolocalisation
optionnelle désactivée par défaut.

### 1.3 Ce qui est corrigé ou ajouté

| Sujet | Version actuelle | Version cible |
|---|---|---|
| Authentification | Compte Google, internet requis au 1er lancement | Profil agent déclaratif, aucun réseau |
| Contrôle d'accès au fichier | Aucun | Code d'accès optionnel, données chiffrées |
| Persistance des saisies | En mémoire, perdues si l'app est tuée | Base locale, écriture à chaque validation |
| `id_index` | Jamais renvoyé | Renvoyé, ainsi que tous les champs reçus |
| Géolocalisation | Présente mais jamais utilisée | Opérationnelle, avec précision enregistrée |
| Téléphone, observation, photo | Absents | Collectés sur le terrain |

---

## 2. Périmètre fonctionnel

### 2.1 Profil de l'agent

- **MOB-100** : au premier lancement, l'agent saisit son **nom** et son **téléphone**.
  Aucune vérification, aucun compte, aucun réseau. Ces informations alimentent le champ
  `agent_export` des fichiers produits.
- **MOB-101** : le profil est modifiable à tout moment depuis les paramètres.

### 2.2 Ouverture d'un fichier de relevé

- **MOB-110** : import d'un fichier `.json` ou `.zip` depuis le gestionnaire de fichiers,
  y compris un fichier reçu par WhatsApp.
- **MOB-111** : si le fichier porte une enveloppe chiffrée (§4), l'application demande le
  **code d'accès** avant de l'ouvrir. Trois tentatives erronées referment le fichier.
- **MOB-112** : **rétrocompatibilité** — un fichier sans enveloppe s'ouvre directement,
  sans rien demander. Tous les exports antérieurs restent exploitables.
- **MOB-113** : plusieurs fichiers peuvent rester ouverts simultanément ; une liste permet
  de basculer de l'un à l'autre, de fermer ou de supprimer un fichier. La suppression est
  irréversible et doit être confirmée explicitement.

### 2.3 Liste des compteurs

- **MOB-120** : compteurs groupés par section (`nom_feuille`), répartis sur trois onglets :
  **tous**, **non relevés** (onglet par défaut), **relevés**.
- **MOB-121** : chaque compteur affiche le nom de l'abonné, le numéro de compteur, l'ancien
  index, et le nouvel index avec sa date une fois saisi.
- **MOB-122** : code couleur — **rouge : non relevé, vert : relevé**. Cette convention est
  celle de la version en production et doit être conservée : les agents y sont habitués.
- **MOB-123** : recherche par nom d'abonné ou numéro de compteur.
- **MOB-124** : un indicateur d'avancement permanent (relevés / total) est visible.

### 2.4 Saisie d'un relevé

- **MOB-130** : la saisie s'ouvre au clic sur un compteur et présente le libellé, la section
  et l'ancien index.
- **MOB-131** : champs saisissables :

  | Champ | Nature | Règle |
  |---|---|---|
  | Nouvel index | numérique, décimal accepté | **≥ ancien index**, sinon refus expliqué |
  | Date du relevé | date | ≤ aujourd'hui, pré-remplie au jour |
  | Numéro de compteur | texte | correction du numéro gravé sur l'appareil |
  | Téléphone de l'abonné | texte | correction de `abone.numero_telephone` (§5.2) |
  | Observation | texte libre | rattachée **au relevé du mois**, historisée |
  | Photo | image | une ou plusieurs, voir §2.5 |

- **MOB-132** : le clavier numérique s'ouvre par défaut sur le champ d'index.
- **MOB-133** : le relevé est **écrit en base locale dès la validation**. Une interruption
  de l'application ne doit jamais faire perdre une saisie.
- **MOB-134** : un relevé déjà saisi reste modifiable.

### 2.5 Photographies

- **MOB-140** : l'agent peut joindre une ou plusieurs photos à un relevé (appareil photo ou
  galerie).
- **MOB-141** : les images sont **redimensionnées et recompressées** avant stockage — cible
  indicative : 1600 px sur le grand côté, JPEG qualité 80, soit environ 200 Ko. Sans cela,
  un lot de 150 compteurs photographiés dépasserait la limite d'envoi du serveur.
- **MOB-142** : l'agent peut supprimer une photo avant l'export.

### 2.6 Géolocalisation

- **MOB-150** : activable dans les paramètres, **désactivée par défaut** — comportement
  actuel conservé.
- **MOB-151** : quand elle est active, la position est capturée pour chaque relevé validé.
- **MOB-152** : l'acquisition démarre **à l'ouverture de la boîte de dialogue**, pas à la
  validation. L'agent saisit son index pendant que le signal s'affine. La documentation
  actuelle signale que la saisie est ralentie par l'attente ; ce point doit disparaître.
- **MOB-153** : la **précision en mètres** est enregistrée à côté des coordonnées, et un
  indicateur visuel la montre à l'agent. Une position trop imprécise doit pouvoir être
  refusée côté serveur.
- **MOB-154** : en l'absence de position, les champs sont envoyés **à zéro franc**, jamais
  à `1.0e-8` comme aujourd'hui.

### 2.7 Export

- **MOB-160** : l'export produit un **JSON simple** s'il n'y a aucune photo, un **ZIP**
  sinon (§3.3).
- **MOB-161** : deux actions — *Exporter* (enregistrer sur l'appareil) et *Envoyer*
  (partage Android : WhatsApp, courriel…).
- **MOB-162** : si le fichier d'origine était protégé par un code, **le fichier de retour
  l'est aussi**, avec le même code.
- **MOB-163** : l'application avertit avant de fermer ou supprimer un fichier contenant des
  relevés jamais exportés.

---

## 3. Format d'échange

### 3.1 Principe directeur : fidélité aller-retour

- **MOB-200** : l'application **conserve la donnée brute de chaque compteur telle que
  reçue** et ne modifie que les champs qu'elle produit. Tout champ ajouté au serveur plus
  tard est restitué intact, sans intervention sur le mobile.

Champs produits par l'application : `nouvel_index`, `date_releve`, `numero`,
`numero_abone`, `observation`, `latitude`, `longitude`, `precision_m`, `photos`.

### 3.2 Structure

```json
{
  "format_version": 2,
  "info_reseau": {
    "nom_reseau": "AEP Bassessa",
    "id_reseau": "12",
    "agent_export": "Nom saisi dans le profil",
    "date_export": "06/09/2026:10/56/47"
  },
  "localiser": true,
  "releve": [
    {
      "nom_feuille": "nom_aep",
      "data": [
        {
          "id": 6553,
          "id_compteur": "7024",
          "id_index": "10277",
          "libele": "AGOKENG BONIFACE",
          "numero": "H21801172",
          "numero_abone": "699123456",
          "reseau": "Rs1",
          "ancien_index": 100,
          "nouvel_index": 104,
          "date_releve": "2026-09-06",
          "observation": "Portail fermé, relevé depuis la rue",
          "latitude": 5.497021,
          "longitude": 10.125690,
          "precision_m": 8.5,
          "photos": ["photos/7024_20260906_101530.jpg"]
        }
      ]
    }
  ]
}
```

- **MOB-201** : `format_version` est ajouté pour permettre au serveur de distinguer les
  générations de fichiers. Son absence signifie « version 1 » (format actuel).
- **MOB-202** : `id_index` est **obligatoire** dans le fichier de retour. C'est lui qui
  permet au serveur de mettre à jour un mois existant ; son absence est la raison pour
  laquelle la version actuelle ne sait que créer un nouveau mois.
- **MOB-203** : `date_releve` est au format **ISO `AAAA-MM-JJ`**.

### 3.3 Paquet avec photos

```
releve_Bassessa_06-09-2026.zip
├── releve.json
└── photos/
    ├── 7024_20260906_101530.jpg
    └── 7057_20260906_103412.jpg
```

- **MOB-210** : la référence à une photo vit **dans la fiche du compteur** (`photos`), et
  non dans un index séparé — aucune désynchronisation possible entre les deux.
- **MOB-211** : nommage `{id_compteur}_{AAAAMMJJ}_{HHMMSS}.jpg`.

---

## 4. Code d'accès et chiffrement

### 4.1 Intention

Protéger un fichier qui circule par WhatsApp et contient des données d'abonnés, des
observations et des photographies. Le code n'est pas stocké : il **dérive** la clé qui
chiffre réellement les données.

- **MOB-220** / **SRV-220** : le code d'accès est **choisi à l'export** dans l'application
  web, et transmis à l'agent par un autre canal (oral, SMS).
- **MOB-221** : il est **optionnel**. Sans code, le fichier reste en clair et s'ouvre
  directement (§2.2).

### 4.2 Protocole

Contraintes vérifiées sur le serveur (PHP 5.3 + OpenSSL) : AES-256-CBC, HMAC-SHA256 et
`openssl_random_pseudo_bytes` sont disponibles ; **AES-GCM et `hash_pbkdf2` ne le sont
pas** et doivent être évités. La dérivation est donc écrite à la main — c'est du PBKDF2
standard, une quinzaine de lignes.

```
sel        = 16 octets aléatoires
cle        = PBKDF2-HMAC-SHA256(code, sel, 100 000 itérations, 32 octets)
iv         = 16 octets aléatoires
chiffre    = AES-256-CBC(donnees_json, cle, iv)
signature  = HMAC-SHA256(sel || iv || chiffre, cle)     ← chiffrer-puis-signer
```

Enveloppe résultante :

```json
{
  "chiffrement": {
    "algo": "aes-256-cbc",
    "kdf": "pbkdf2-hmac-sha256",
    "iterations": 100000,
    "sel": "<base64>",
    "iv": "<base64>",
    "signature": "<base64>"
  },
  "donnees": "<base64 du contenu chiffré>"
}
```

- **MOB-222** : la signature est vérifiée **avant** toute tentative de déchiffrement. Un
  code erroné se détecte par l'échec de la signature, sans exposer de contenu.
- **MOB-223** : dans un paquet ZIP, seul `releve.json` est chiffré ; les photos restent
  telles quelles. Les chiffrer aussi alourdirait le traitement sans rien protéger de plus,
  puisque l'essentiel de l'information nominative est dans le JSON.

> **Conséquence assumée** : un code perdu rend le fichier illisible. Ce n'est pas bloquant,
> le serveur pouvant toujours régénérer l'export — mais un fichier de retour perdu l'est
> définitivement.

Le protocole est délibérément classique, afin d'être réimplémentable sans difficulté en
Dart (`pointycastle`) aujourd'hui, et en Go pour la v2 demain.

---

## 5. Impacts sur l'application web et la base

### 5.1 Export protégé

- **SRV-230** : la page **Relevés** reçoit un champ « code d'accès » optionnel à côté du
  bouton d'export. Renseigné, il déclenche le chiffrement décrit en §4.
- **SRV-231** : `Abones::getJsonDataFromIdMois()` ajoute `format_version` et renvoie les
  coordonnées réelles (déjà fait) ainsi qu'un champ `observation` vide.

### 5.2 Import enrichi

- **SRV-240** : l'import accepte **JSON et ZIP**. Pour le ZIP : extraction de
  `releve.json`, puis des photos, avec **les mêmes protections que l'import de tuiles**
  (liste blanche d'extensions, validation que chaque fichier est réellement une image,
  rejet de tout chemin contenant `..`, plafonds de taille et de nombre).
- **SRV-241** : déchiffrement préalable si l'enveloppe est présente ; le code est demandé
  dans le formulaire d'import.
- **SRV-242** : `MoisFacturation::updateIndexFronFile()` enregistre en plus l'observation,
  le téléphone corrigé et les photos. Les coordonnées le sont déjà.
- **SRV-243** : le téléphone corrigé met à jour **`abone.numero_telephone`**. Aucune
  nouvelle colonne.
- **SRV-244** : un rapport d'import récapitule ce qui a été appliqué et ce qui a été écarté,
  avec le motif — comme le fait déjà l'affectation de coordonnées.

### 5.3 Nouvelles structures

Créées selon le motif `ensureTable()` en vigueur dans le projet.

```sql
-- Observation rattachée au relevé du mois (historisée)
CREATE TABLE releve_observation (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_index      INT UNSIGNED NOT NULL,
    observation   TEXT,
    agent         VARCHAR(128),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_index (id_index)
);

-- Photographies prises sur le terrain
CREATE TABLE releve_photo (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_compteur   INT UNSIGNED NOT NULL,
    id_index      INT UNSIGNED DEFAULT NULL,
    fichier       VARCHAR(255) NOT NULL,
    prise_le      DATETIME DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_compteur (id_compteur),
    KEY idx_index (id_index)
);
```

- **SRV-250** : les photos sont stockées sous `donnees/photos/aep_<id>/`, **hors de tout
  chemin exécutable**, et servies par un point d'entrée contrôlé.
- **SRV-251** : ce dossier est **exclu des sauvegardes SQL** — comme celui des tuiles.

### 5.4 Restitution

- **SRV-260** : la fiche abonné affiche les observations et les photos du relevé.
- **SRV-261** : la carte (page Cartographie) affiche la photo dans l'infobulle d'un
  compteur qui en possède une.

---

## 6. Architecture de l'application

### 6.1 Pile

| Besoin | Choix |
|---|---|
| Cadre | Flutter, Dart 3, cible Android |
| État | Riverpod |
| Persistance | SQLite (Drift ou sqflite) |
| Chiffrement | `pointycastle` |
| Position | `geolocator` + `permission_handler` |
| Photos | `image_picker` + `flutter_image_compress` |
| Fichiers | `file_picker`, `share_plus`, `path_provider`, `archive` |

Aucun client HTTP : l'échange reste fondé sur les fichiers.

### 6.2 Découpage

```
lib/
├── domain/        entités et règles métier, sans dépendance technique
├── data/          base locale, lecture/écriture des fichiers, chiffrement
└── presentation/  écrans, composants, état
```

- **MOB-300** : les règles métier (index croissant, validité d'une position, complétude
  d'un relevé) résident dans `domain/` et sont **couvertes par des tests unitaires**. Le
  projet actuel n'a pour tout test que le *counter smoke test* du gabarit Flutter.
- **MOB-301** : la base locale est la source de vérité pendant la tournée ; l'export n'en
  est qu'une projection.

---

## 7. Exigences non fonctionnelles

- **MOB-400** : l'application fonctionne **intégralement hors connexion**, du premier
  lancement à l'export.
- **MOB-401** : aucune saisie validée ne peut être perdue, quelle que soit la manière dont
  l'application se termine.
- **MOB-402** : lisibilité en plein soleil — contrastes élevés, cibles tactiles larges.
  L'usage se fait dehors, souvent à une main.
- **MOB-403** : un lot de 150 compteurs avec photos doit rester sous la limite d'envoi du
  serveur, actuellement **20 Mo**.
- **MOB-404** : les messages d'erreur sont explicites et en français ; aucun échec
  silencieux.

---

## 8. Ce qui est repris de l'existant

Le prototype de mai 2024 apporte trois choses, et rien d'autre :

- le **contrat JSON** et la hiérarchie des modèles (AEP → Feuille → Compteur) ;
- le principe d'ergonomie — liste de cartes colorées selon l'état, clic pour saisir ;
- l'inventaire de ses propres défauts, à ne pas reproduire : chemin de fichier codé en dur
  lu au démarrage, contournement `+0.00000001` sur la latitude, couleurs inversées,
  `DateFormat('dd/mm/yyyy')` affichant les minutes, formulaire dont la validation de date
  ne peut jamais réussir, clé `info_resau` mal orthographiée, bouton d'enregistrement vide.

---

## 9. Points encore ouverts

1. **Format produit quand `localiser` vaut `true`** — aucun fichier de référence n'existe,
   les cinq exports retrouvés ayant tous `localiser: false`. La structure retenue en §3.2
   est une proposition ; un export réel de l'APK en production permettrait de la confronter.
2. **Seuil de précision GPS** au-delà duquel une position est refusée. À caler après les
   premiers relevés terrain.
3. **Politique de conservation des photos** — durée, volume maximal par AEP, purge.
