SITE EN LIGNE: https://www.avis-campus.fr/

# avis-campus

Plateforme d'avis étudiants sur les cours (Symfony 7.4 / PHP 8.2 / PostgreSQL).

---

## Installation & mise en route

### Prérequis

| Outil                   | Version     | Remarque                                                                   |
| ----------------------- | ----------- | -------------------------------------------------------------------------- |
| PHP                     | ≥ 8.2       | extensions `ctype`, `iconv`, `pdo_pgsql`, `pdo_sqlite`, `intl`, `mbstring` |
| Composer                | 2.x         | gestion des dépendances PHP                                                |
| Docker + Docker Compose | récent      | PostgreSQL, Adminer et Mailpit                                             |
| Symfony CLI             | _optionnel_ | serveur de dev pratique (`symfony serve`)                                  |

### 1. Récupérer le projet et les dépendances

```bash
git clone https://github.com/vithurzen/avis-campus avis-campus
cd avis-campus
composer install
```

### 2. Monter les conteneurs Docker

Le fichier `compose.yaml` fournit trois services :

```bash
docker compose up -d
```

| Service    | Rôle                               | Accès (hôte)                                       |
| ---------- | ---------------------------------- | -------------------------------------------------- |
| `database` | PostgreSQL 16 (base `avis_campus`) | `127.0.0.1:5433`                                   |
| `adminer`  | Explorateur de base de données     | http://localhost:8080                              |
| `mailpit`  | Serveur SMTP de test + webmail     | SMTP `127.0.0.1:11025` — UI http://127.0.0.1:18026 |

La connexion à la base est déjà configurée dans `.env` :

```
DATABASE_URL="postgresql://avis_campus:password@127.0.0.1:5433/avis_campus?serverVersion=16&charset=utf8"
```

En développement, les e-mails sont routés vers Mailpit via `.env.local`
(`MAILER_DSN=smtp://127.0.0.1:11025`) : tous les mails envoyés sont visibles dans
l'interface http://127.0.0.1:18026 (aucun envoi réel).

### 3. Lancer les migrations

Crée le schéma de la base `avis_campus` :

```bash
php bin/console doctrine:migrations:migrate
```

### 4. Charger les fixtures (jeu de données de démonstration)

```bash
php bin/console doctrine:fixtures:load
```

Les fixtures (`src/DataFixtures/AppFixtures.php`) créent notamment :
10 tags, 5 critères de notation, 5 enseignants, 3 formations et leurs semestres,
20 cours, **2 administrateurs, 3 modérateurs et 20 étudiants**, ainsi que
~50 avis, ~100 notations, ~30 commentaires et ~10 signalements.

### 5. Démarrer l'application

```bash
symfony serve -d                 # http://127.0.0.1:8000
# ou, sans la CLI Symfony :
php -S 127.0.0.1:8000 -t public
```

---

## Exécuter les tests

Les tests utilisent une base **SQLite** dédiée (`.env.test`) : **ni PostgreSQL ni
Docker ne sont nécessaires** pour les lancer. La séquence ci-dessous reproduit
exactement celle de la CI (`.github/workflows/deploy.yml`).

```bash
# 1. Créer le schéma de la base de test (SQLite : var/data_test.db)
php bin/console doctrine:schema:create --env=test

# 2. Charger les fixtures dans la base de test
php bin/console doctrine:fixtures:load --no-interaction --env=test

# 3. Lancer la suite de tests
php vendor/bin/phpunit
```

Analyse statique (également exécutée en CI) :

```bash
php vendor/bin/phpstan analyse --memory-limit=1G
```

---

## Comptes de test

Tous les comptes générés par les fixtures partagent le **mot de passe : `password`**.

Les comptes ci-dessous possèdent une adresse **déterministe** (toujours identique
après un chargement des fixtures) :

| Rôle                          | E-mail                | Mot de passe |
| ----------------------------- | --------------------- | ------------ |
| Administrateur (`ROLE_ADMIN`) | `admin@admin.fr`      | `password`   |
| Étudiant (`ROLE_STUDENT`)     | `etudiant1@campus.fr` | `password`   |

Le second administrateur, les **3 modérateurs** (`ROLE_MODERATOR`) et les 19 autres
étudiants reçoivent des adresses e-mail générées aléatoirement. Pour les lister
après avoir chargé les fixtures :

```bash
# Modérateurs
php bin/console dbal:run-sql "SELECT email FROM users WHERE roles::text LIKE '%MODERATOR%'"

# Tous les comptes, triés par rôle
php bin/console dbal:run-sql "SELECT email, roles FROM users ORDER BY roles"
```

> Astuce : on peut aussi parcourir la table `users` via Adminer (http://localhost:8080).

---

## Sécurité

### Authentification

L'authentification repose sur le composant Security de Symfony (firewall `main`,
provider Doctrine basé sur l'entité `App\Entity\User`, propriété `email`).

| Action      | Route                                 | Détails                                                                                                                                                                                                                                    |
| ----------- | ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Inscription | `GET/POST /register` (`app_register`) | `RegistrationController` + `RegistrationFormType`. Le mot de passe est hashé via `UserPasswordHasherInterface` (algorithme `auto`), l'utilisateur reçoit le rôle `ROLE_STUDENT`, un `StudentProfile` est créé, puis connexion automatique. |
| Connexion   | `GET/POST /login` (`app_login`)       | `SecurityController`, authentificateur `form_login` (CSRF activé : `enable_csrf: true`).                                                                                                                                                   |
| Déconnexion | `GET /logout` (`app_logout`)          | Interceptée par le firewall (`logout.path: app_logout`).                                                                                                                                                                                   |

### Rôles et hiérarchie

Les rôles sont stockés sur `User` (colonne JSON `roles`) ; `getRoles()` garantit
toujours `ROLE_USER`. Hiérarchie (`config/packages/security.yaml`) :

```yaml
role_hierarchy:
    ROLE_STUDENT: ROLE_USER
    ROLE_MODERATOR: ROLE_USER
    ROLE_ADMIN: [ROLE_MODERATOR, ROLE_USER]
```

Ainsi un `ROLE_ADMIN` hérite des droits modérateur, et tout utilisateur connecté
possède `ROLE_USER`.

### Protection des pages privées

Deux niveaux de contrôle d'accès :

1. **Par URL** (`access_control`) :

    ```yaml
    access_control:
        - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/moderation, roles: ROLE_MODERATOR }
        - { path: ^/review/new, roles: ROLE_USER }
    ```

2. **Par action** via l'attribut `#[IsGranted(...)]` (ex. `ModerationController`
   est protégé au niveau de la classe par `#[IsGranted('ROLE_MODERATOR')]` ; les
   actions favoris par `#[IsGranted('ROLE_USER')]`).

### Voter personnalisé — `ReviewVoter`

`src/Security/Voter/ReviewVoter.php` applique des règles au niveau de l'objet
`Review` (au-delà du simple rôle) :

| Attribut                                           | Autorisé si                                                    |
| -------------------------------------------------- | -------------------------------------------------------------- |
| `REVIEW_EDIT`                                      | admin, **ou** auteur **et** avis encore en attente (`pending`) |
| `REVIEW_DELETE`                                    | admin, **ou** auteur **et** avis encore `pending`              |
| `REVIEW_APPROVE` / `REVIEW_REJECT` / `REVIEW_HIDE` | `ROLE_MODERATOR` (les admins en héritent)                      |

Un administrateur peut tout faire (court-circuit `isGranted('ROLE_ADMIN')`).
Le voter est câblé dans les contrôleurs via
`#[IsGranted(ReviewVoter::EDIT, subject: 'review')]`, etc.

### Favoris (cours)

Ajout/retrait d'un cours en favori (réservé aux utilisateurs connectés, protégé
par jeton CSRF, limité au propriétaire) :

| Action                      | Route                                                       |
| --------------------------- | ----------------------------------------------------------- |
| Ajouter / retirer (bascule) | `POST /favorite/course/{id}/toggle` (`app_favorite_toggle`) |
| Supprimer un favori précis  | `POST /favorite/{id}/remove` (`app_favorite_remove`)        |

### Modération automatique du contenu

À la soumission d'un avis, `ExternalModerationService` analyse le texte et
renvoie un verdict (`safe`, `aggressive`, `needs_review`) avec une éventuelle
reformulation. Un contenu `aggressive` est automatiquement rejeté. Le service
interroge une API externe si `EXTERNAL_MODERATION_URL` est défini, sinon une
analyse locale de secours est utilisée. Chaque appel est journalisé dans
`ApiLog`.
