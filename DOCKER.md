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

Connexion PHP : `donnees/db_config.php` (variables `DB_*`).

### WAMP sans Docker

`localhost` / `root` / mot de passe vide, ou `donnees/config.local.php`.

## Base de donnees

Au premier demarrage, import automatique de `docker/init-db/01-schema.sql`.

Import manuel d’un backup :

```bat
docker compose exec -T db mysql -uroot -proot suivi_aep_fokoue < C:\chemin\backup.sql
```

## Depannage

- **Port occupe** : changer `APP_PORT` dans `.env`.
- **Erreur MySQL** : `docker compose logs db` — attendre `healthy`.
- **`compile_mysql51.sh`** : ancienne methode abandonnee ; utiliser `docker compose pull` puis `up`.

## Fichiers

- `docker-compose.yml` — services `web` + `db`
- `Dockerfile` — couche legere sur `kochanup/apache-2.2-php-5.3`
- `docker/init-db/01-schema.sql` — schema initial
