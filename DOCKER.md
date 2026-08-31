# Docker — stack legacy (versions eprouvees)

Images **pre-construites** sur Docker Hub, proches de votre WAMP :

| Composant | WAMP (cible) | Docker (utilise) |
|-----------|--------------|------------------|
| **Apache** | 2.2.17 | 2.2.x (`kochanup/apache-2.2-php-5.3`) |
| **PHP** | 5.3.4 | **5.3.17** (meme branche 5.3) |
| **MySQL** | 5.1.53 | **5.1.73** (meme branche 5.1, compatible) |

Le premier demarrage prend **quelques minutes** (telechargement des images), pas 30 min de compilation.

## Demarrage

```bat
cd c:\wamp\www\fokoue\suivi_reseau
docker compose down -v
docker\docker-up.bat
```

> `down -v` efface l’ancienne base si vous aviez tente le build MySQL compile.

Application : **http://localhost:8080/index.php**

Verifier les versions :

```bat
docker\check-versions.bat
```

## Commandes

| Action | Commande |
|--------|----------|
| Demarrer | `docker compose up -d` |
| Build web | `docker compose build web` |
| Arreter | `docker compose down` |
| Logs | `docker compose logs -f web` |

## Configuration (`.env`)

| Variable | Defaut |
|----------|--------|
| `APP_PORT` | 8080 |
| `MYSQL_PORT` | 3307 |
| `DB_NAME` | suivi_aep_fokoue |
| `DB_USER` / `DB_PASSWORD` | suivi / suivi |
| `MYSQL_ROOT_PASSWORD` | root |

Connexion PHP : `donnees/db_config.php` (variables `DB_*`). Dans Docker, `config.local.php` WAMP (`localhost`) est ignore pour la BD — hôte = `db`.

Import backup SQL : page **Sauvegardes** (PDO vers le service `db`, pas le socket local).

Dossier **`backups/`** à la racine du projet (monté dans le conteneur web, droits `daemon` via `docker/entrypoint.sh`).

### WAMP sans Docker

`localhost` / `root` / mot de passe vide, ou `donnees/config.local.php`.

## Base de donnees

Au demarrage, le service **`db-init`** importe **`app-setup/App_vide.sql`** si la table `aep` est absente (l’image `vsamov/mysql` **n’execute pas** `/docker-entrypoint-initdb.d`).

Si `SHOW TABLES` est vide :

```bat
docker compose run --rm db-init
```

ou :

```bat
docker\import-db.bat
```

Fichier utilise : **`app-setup/App_vide.sql`** (reference unique pour l’initialisation).

Ensuite, appliquez les migrations PHP si necessaire :  
`http://localhost:8080/donnees/bd/update_all.php?run_update=1`

Import manuel d’un autre backup :

```bat
docker compose cp "C:\chemin\backup.sql" db:/tmp/backup.sql
docker compose exec db sh -c "mysql -uroot -proot suivi_aep_fokoue < /tmp/backup.sql"
```

Repartir de zero (efface toutes les donnees Docker) :

```bat
docker compose down -v
docker compose up -d
docker\import-db.bat
```

## Depannage

- **`TLS handshake timeout` sur `kochanup/apache-2.2-php-5.3`** : Docker Hub inaccessible (reseau lent, VPN, pare-feu). **Ne pas utiliser** `docker compose up -d --build` si l'image existe deja. Demarrer avec :
  ```bat
  docker compose up -d
  ```
  Rebuild seulement quand le `Dockerfile` change et que le reseau fonctionne :
  ```bat
  docker compose build web
  ```
  Ou telecharger l'image de base une fois :
  ```bat
  docker pull kochanup/apache-2.2-php-5.3
  docker pull vsamov/mysql-5.1.73
  ```
- **404 sur `/index.php`** : l'image kochanup sert par defaut `/opt/apache-2.2/htdocs`. Le compose monte `docker/apache/default_80.conf` vers `/var/www/html`. Apres modification : `docker compose up -d` (ou `restart web`).
- **Page blanche** : l'image PHP n'a pas `openssl` → erreur fatale sur `active.lic`. Corrige dans `licence_crypto.php`. En Docker, `LICENCE_DEV_MODE=1` (defaut dans compose) permet d'utiliser l'app ; pour tester l'import `.lic`, utiliser **WAMP** ou mettre `LICENCE_DEV_MODE=0` et installer OpenSSL dans l'image.
- **Port occupe** : changer `APP_PORT` dans `.env`.
- **Erreur MySQL** : `docker compose logs db` — attendre `healthy`.
- **`compile_mysql51.sh`** : ancienne methode abandonnee ; utiliser `docker compose pull` puis `up`.

## Fichiers

- `docker-compose.yml` — services `web` + `db`
- `Dockerfile` — couche legere sur `kochanup/apache-2.2-php-5.3`
- `app-setup/App_vide.sql` — schema initial (Docker init + `docker\import-db.bat`)
