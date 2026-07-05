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
| Front | Twig + **TailwindCSS** (AssetMapper + tailwind-bundle, sans Node) |
| Asynchrone | Symfony Messenger (transport Doctrine) |
| Tests | PHPUnit 12 + DAMA DoctrineTestBundle (isolation transactionnelle) |
| Analyse statique | PHPStan (niveau 6) |
| Conteneurisation | Docker / Docker Compose |

Tout s'exécute **dans des conteneurs Docker** : aucun PHP/Composer/Node local n'est requis.

---

## Prérequis

- [Docker](https://docs.docker.com/get-docker/) **et** Docker Compose v2 (`docker compose`)
- Ports libres : **8080** (web) et **5432** (PostgreSQL) — ajustables via `HTTP_PORT` / `POSTGRES_PORT`

---

## Installation (premier démarrage)

```bash
# 1. Récupérer le projet
git clone <url-du-dépôt> buzzer && cd buzzer

# 2. (optionnel) aligner l'utilisateur des conteneurs sur le vôtre
echo "UID=$(id -u)" >> .env.local
echo "GID=$(id -g)" >> .env.local

# 3. (optionnel) clé API NBA — nécessaire uniquement pour `app:nba:sync`
echo "SPORT_API=votre_clef_api_sports" >> .env.local

# 4. Construire et démarrer la stack (php + nginx + postgres)
docker compose build
docker compose up -d

# 5. Installer les dépendances PHP
docker compose exec php composer install

# 6. Compiler le CSS (TailwindCSS, binaire standalone — pas de npm)
docker compose exec php php bin/console tailwind:build --minify

# 7. Créer la base et appliquer les migrations
docker compose exec php php bin/console doctrine:database:create --if-not-exists
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# 8. Charger le jeu de données de démonstration (fixtures)
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

L'application est disponible sur **<http://localhost:8080>**.

> 💡 Après chaque `git pull` qui modifie `composer.json` : relancez `composer install`.
> Pendant le développement front : `php bin/console tailwind:build --watch`.

---

## Jeu de données de démonstration (fixtures)

`src/DataFixtures/AppFixtures.php` (DoctrineFixturesBundle + Faker) charge un jeu
**complet et réaliste** permettant de tester chaque module immédiatement :

- **9 utilisateurs** (3 comptes de rôle + 6 membres), tous e-mail vérifié ;
- **12 vraies équipes NBA** et **36 vraies stars** (LeBron, Curry, Jokić, Dončić…) ;
- **15 matchs** : **6 à venir** (⚠️ dates **relatives**, donc toujours ouverts aux
  pronostics), 1 en direct, 8 terminés avec scores officiels ;
- **50 pronostics** : en attente sur les matchs futurs, réglés (gagnés/perdus + points)
  sur les matchs terminés — les 3 types (vainqueur, score exact, performance joueur) ;
- **2 ligues** avec classements cohérents, **8 commentaires** (dont 2 masqués pour la
  démo de modération), **badges** attribués selon les points réels, **notifications**.

### Comptes de test (mot de passe commun : `password`)

| Rôle | E-mail | Pseudo | Notes |
|---|---|---|---|
| `ROLE_ADMIN` | `admin@buzzer.test` | admin | Propriétaire de la « Ligue des Internes » |
| `ROLE_MANAGER` | `manager@buzzer.test` | manager | Modération · propriétaire de « NBA Fanatics » |
| `ROLE_USER` | `user@buzzer.test` | parieur | **Compte de démo principal** (pronos, badges, 2 ligues) |
| `ROLE_USER` | `marie@…` `lucas@…` `sofia@…` `hugo@…` `lea@…` `noah@buzzer.test` | — | Membres pour peupler classements et commentaires |

Les rôles sont **hiérarchiques** : `ROLE_ADMIN` → `ROLE_MANAGER` → `ROLE_USER`.

---

## Commandes CLI du projet

### `app:nba:sync` — synchronisation du référentiel NBA

Consomme l'API [api-sports.io](https://api-sports.io) via le HttpClient scopé
(`SPORT_API` dans `.env.local`). Upsert **idempotent** par identifiant API : aucun doublon.

```bash
docker compose exec php php bin/console app:nba:sync              # équipes + matchs
docker compose exec php php bin/console app:nba:sync --teams      # équipes seulement
docker compose exec php php bin/console app:nba:sync --games --date=2026-07-04
docker compose exec php php bin/console app:nba:sync --players --season=2024
```

> ⚠️ **Plan gratuit api-sports** : `/games` n'est accessible que sur une fenêtre de
> ~3 jours autour de la date du jour (et la NBA est en intersaison l'été). Les
> **équipes** se synchronisent sans restriction. Pour la démo, préférez les fixtures.

### `app:predictions:settle` — règlement des pronostics

Règle tous les matchs terminés ayant des pronostics en attente : statut `WON`/`LOST`,
points (barème RG-04), **répercussion sur les classements de ligue** (RG-05),
**attribution des badges** (RG-10) et notifications. **Idempotent** (RG-03).

```bash
docker compose exec php php bin/console app:predictions:settle           # immédiat
docker compose exec php php bin/console app:predictions:settle --async   # via la file Messenger
docker compose exec php php bin/console messenger:consume async -v      # worker
```
---

## Lancer les contrôles qualité (identiques à la CI)

```bash
docker compose exec php php bin/console lint:yaml config --parse-tags
docker compose exec php php bin/console lint:twig templates
docker compose exec php php bin/console lint:container
docker compose exec php vendor/bin/phpstan analyse

# Tests (base dédiée *_test, isolée par transaction grâce à DAMA)
docker compose exec php php bin/console doctrine:database:create --if-not-exists --env=test
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction --env=test
docker compose exec php php bin/phpunit
```

La suite couvre notamment : la **logique de scoring** de chaque type de pronostic
(unitaire), le **service de règlement** (idempotence, classements), le
**PredictionVoter**, le **BadgeAwardService**, la **synchro NBA** (MockHttpClient),
le **handler Messenger** et des parcours fonctionnels (pages, formulaire dynamique).

---

## Modèle de données (15 entités)

- **Héritage (Single Table Inheritance)** : `Prediction` (abstraite) →
  `MatchWinnerPrediction`, `ScorePrediction`, `PlayerPropPrediction` (discriminant `type`).
- **ManyToMany** : favoris `User` ↔ `Team` ; `LeagueMembership` et `UserBadge`
  (associations avec attributs de liaison).
- **16 relations ManyToOne/OneToMany** ; énumérations PHP (`GameStatus`,
  `PredictionStatus`, `StatType`, `Comparison`, `LeagueRole`, `NotificationType`).

| Domaine | Entités |
|---|---|
| Référentiel NBA | `Season`, `Team`, `Player`, `Game` |
| Comptes | `User` (vérification e-mail) |
| Pronostics | `Prediction` + 3 sous-types |
| Ligues | `League`, `LeagueMembership` |
| Social | `Comment` (modération : masquage) |
| Gamification | `Badge`, `UserBadge`, `Notification` |

---

## Sécurité

- Authentification par **formulaire natif** (`LoginFormAuthenticator`), CSRF, *remember me* ;
- **Inscription avec confirmation par e-mail** (VerifyEmailBundle) ;
- Provider Doctrine (identifiant = e-mail), mots de passe hachés (`auto`) ;
- **Hiérarchie de rôles** + `access_control` (`config/packages/security.yaml`) ;
- **3 Voters** : `PredictionVoter` (éditer/annuler son prono avant tip-off),
  `LeagueVoter` (gérer sa ligue), `CommentVoter` (éditer son commentaire / modérer).

---

## Architecture notable

- **Services métier** : `ScoringPolicy` (barème), `PredictionSettlementService`
  (règlement UC-52), `BadgeAwardService` (gamification UC-54), `NbaSynchronizer` (UC-50/51) ;
- **Messenger** : `SettleGameMessage` → file Doctrine → worker (`messenger:consume`) ;
- **Repositories QueryBuilder** anti-N+1 (calendrier paginé, classements, historique) ;
- **Formulaire dynamique** de pronostic (Form Events `PRE_SET_DATA` / `PRE_SUBMIT`) ;
- **Filtre Twig personnalisé** `status_class` (statuts → styles).

---

## Intégration continue

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) à chaque push/PR, sur un
service PostgreSQL : lint Symfony (YAML, Twig, conteneur) → **PHPStan** →
build Tailwind → **PHPUnit**.

---

## Commandes utiles

```bash
docker compose up -d                  # démarrer la stack
docker compose down                   # arrêter
docker compose down -v                # arrêter + purger la base (volume)
docker compose logs -f php            # logs applicatifs
docker compose exec php bash          # shell dans le conteneur
docker compose exec php php bin/console tailwind:build --watch   # CSS en continu
docker compose exec php php bin/console doctrine:migrations:diff # nouvelle migration
```

---

## Arborescence

```
assets/            CSS Tailwind (app.css) + JS (AssetMapper)
docker/            Dockerfile PHP, config Nginx & PHP
config/            Configuration Symfony (packages, routes, security)
src/
  Command/         app:nba:sync · app:predictions:settle
  Controller/      Home, Security, Registration, Game, Prediction, League,
                   Profile, Comment, Moderation
  Entity/          Les 15 entités Doctrine
  Enum/            Énumérations métier
  Form/            Formulaires (dont PredictionType dynamique)
  Message(Handler)/ Règlement asynchrone (Messenger)
  Repository/      Requêtes QueryBuilder optimisées
  Security/        LoginFormAuthenticator + Voters
  Service/         Scoring, Gamification, Nba (HttpClient)
  DataFixtures/    Jeu de données de démonstration
templates/         Vues Twig (héritage de blocs, thème de formulaire Tailwind)
tests/             Unitaires, intégration & fonctionnels
migrations/        Migrations Doctrine
```
