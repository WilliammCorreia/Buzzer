# 🏀 Buzzer — Plateforme de pronostics NBA

Application web de pronostics NBA (sans enjeu monétaire) développée avec **Symfony 7.4**.
Les utilisateurs pronostiquent les matchs, s'affrontent dans des ligues privées et
progressent via un système de classements, badges et notifications.

> Projet de fin de cycle — voir le [cahier des charges](cahier-des-charges-buzzer.md).

---

## Stack technique

| Composant | Version |
|---|---|
| PHP | 8.3 (FPM) |
| Symfony | 7.4 |
| Base de données | PostgreSQL 16 |
| Serveur web | Nginx |
| ORM | Doctrine ORM 3 + Migrations |
| Front | Twig + Bootstrap 5 (CDN) |
| Tests | PHPUnit 12 |
| Analyse statique | PHPStan (niveau 6) |
| Conteneurisation | Docker / Docker Compose |

Tout s'exécute **dans des conteneurs Docker** : aucun PHP/Composer local n'est requis.

---

## Prérequis

- [Docker](https://docs.docker.com/get-docker/) **et** Docker Compose v2 (`docker compose`)
- Le port **8080** (web) et **5432** (PostgreSQL) libres sur la machine hôte
  (ajustables via les variables `HTTP_PORT` / `POSTGRES_PORT`)

---

## Installation (premier démarrage)

```bash
# 1. Récupérer le projet
git clone <url-du-dépôt> buzzer && cd buzzer

# 2. (optionnel) aligner l'utilisateur des conteneurs sur le vôtre
#    pour éviter tout problème de permissions sur les fichiers générés
echo "UID=$(id -u)"  >> .env.local
echo "GID=$(id -g)"  >> .env.local

# 3. Construire l'image PHP et démarrer la stack (php + nginx + postgres)
docker compose build
docker compose up -d

# 4. Installer les dépendances PHP (vendor/ n'est pas versionné)
docker compose run --rm php composer install

# 5. Créer la base et appliquer les migrations
docker compose run --rm php php bin/console doctrine:database:create --if-not-exists
docker compose run --rm php php bin/console doctrine:migrations:migrate --no-interaction

# 6. Charger le jeu de données de test (fixtures)
docker compose run --rm php php bin/console doctrine:fixtures:load --no-interaction
```

L'application est alors disponible sur **<http://localhost:8080>**.

> 💡 Les commandes ci-dessus sont longues ; vous pouvez ouvrir un shell dans le
> conteneur avec `docker compose exec php bash` puis lancer directement
> `php bin/console ...` et `composer ...`.

---

## Comptes de test

Chargés par les fixtures. **Mot de passe commun : `password`**.

| Rôle | E-mail (identifiant) | Pseudo | Permissions |
|---|---|---|---|
| `ROLE_ADMIN` | `admin@buzzer.test` | admin | Administration complète |
| `ROLE_MANAGER` | `manager@buzzer.test` | manager | Modération, badges, stats |
| `ROLE_USER` | `user@buzzer.test` | parieur | Pronostics, ligues, commentaires |

Les rôles sont **hiérarchiques** : `ROLE_ADMIN` → `ROLE_MANAGER` → `ROLE_USER`.

---

## Lancer les contrôles qualité (identiques à la CI)

```bash
# Lint (YAML, Twig, conteneur de services)
docker compose run --rm php php bin/console lint:yaml config --parse-tags
docker compose run --rm php php bin/console lint:twig templates
docker compose run --rm php php bin/console lint:container

# Analyse statique
docker compose run --rm php vendor/bin/phpstan analyse

# Tests (prépare d'abord la base de test)
docker compose run --rm php php bin/console doctrine:database:create --if-not-exists --env=test
docker compose run --rm php php bin/console doctrine:migrations:migrate --no-interaction --env=test
docker compose run --rm php php bin/phpunit
```

La suite contient :
- un **test unitaire** sur la logique de scoring des pronostics
  (`tests/Unit/Entity/PredictionScoringTest.php`) ;
- un **test fonctionnel** sur les pages publiques (`tests/Functional/SmokeTest.php`).

---

## Modèle de données (15 entités)

Le modèle reflète le [MCD figé](cahier-des-charges-buzzer.md). Points notables :

- **Héritage (Single Table Inheritance)** : `Prediction` (abstraite) →
  `MatchWinnerPrediction`, `ScorePrediction`, `PlayerPropPrediction`
  (colonne discriminante `type`).
- **ManyToMany** : favoris `User` ↔ `Team` (sans attribut) ;
  `User` ↔ `League` via `LeagueMembership` et `User` ↔ `Badge` via `UserBadge`
  (associations **avec attributs de liaison**).
- **ManyToOne / OneToMany** : 16 relations (matchs ↔ équipes/saison,
  joueurs ↔ équipe, commentaires, notifications, pronostics, etc.).
- Énumérations PHP : `GameStatus`, `PredictionStatus`, `StatType`, `Comparison`,
  `LeagueRole`, `NotificationType`.

| Domaine | Entités |
|---|---|
| Référentiel NBA | `Season`, `Team`, `Player`, `Game` |
| Comptes | `User` |
| Pronostics | `Prediction` (+ 3 sous-types) |
| Ligues | `League`, `LeagueMembership` |
| Social | `Comment` |
| Gamification | `Badge`, `UserBadge`, `Notification` |

---

## Sécurité

- Authentification par **formulaire natif** (`LoginFormAuthenticator`) avec
  protection **CSRF** et *remember me*.
- Provider d'utilisateurs **Doctrine** (identifiant = e-mail), mots de passe
  hachés (`auto`).
- **Hiérarchie de rôles** et `access_control` par préfixe d'URL (`config/packages/security.yaml`).
- Les permissions liées à la propriété d'une ressource (éditer son pronostic,
  administrer sa ligue…) seront déléguées à des **Voters** dédiés
  (prochaine étape, cf. cahier des charges §3.4).

---

## Intégration continue

Le workflow [`.github/workflows/ci.yml`](.github/workflows/ci.yml) s'exécute à
chaque *push* / *pull request* et enchaîne, sur un service PostgreSQL :

1. Lint Symfony (YAML, Twig, conteneur)
2. Analyse statique **PHPStan**
3. Suite de **tests PHPUnit**

---

## Commandes utiles

```bash
docker compose up -d                 # démarrer la stack
docker compose down                  # arrêter
docker compose down -v               # arrêter + supprimer le volume base de données
docker compose logs -f php           # logs PHP
docker compose exec php bash         # shell dans le conteneur applicatif
docker compose run --rm php php bin/console doctrine:migrations:diff   # générer une migration
```

---

## Arborescence

```
docker/            Dockerfile PHP, config Nginx & PHP
config/            Configuration Symfony (packages, routes, security)
src/
  Controller/      Contrôleurs (Home, Security)
  Entity/          Les 15 entités Doctrine
  Enum/            Énumérations métier
  Repository/      Repositories (+ requêtes QueryBuilder optimisées)
  Security/        LoginFormAuthenticator
  DataFixtures/    Jeu de données de test
templates/         Vues Twig (base + blocs, accueil, login)
tests/             Tests unitaires & fonctionnels
migrations/        Migrations Doctrine
```
