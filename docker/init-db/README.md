# Init base Docker

Le schema initial est **`app-setup/App_vide.sql`** (monte dans `docker-compose.yml`).

L’ancien fichier `01-schema.sql` n’est plus utilise.

Import manuel (base deja creee sans tables) :

```bat
docker\import-db.bat
```
