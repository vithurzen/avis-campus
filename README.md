# avis-campus

Plateforme d'avis étudiants sur les cours (Symfony 7.4 / PHP 8.2 / PostgreSQL).

## Sécurité

### Authentification

L'authentification repose sur le composant Security de Symfony (firewall `main`,
provider Doctrine basé sur l'entité `App\Entity\User`, propriété `email`).

| Action | Route | Détails |
|--------|-------|---------|
| Inscription | `GET/POST /register` (`app_register`) | `RegistrationController` + `RegistrationFormType`. Le mot de passe est hashé via `UserPasswordHasherInterface` (algorithme `auto`), l'utilisateur reçoit le rôle `ROLE_STUDENT`, un `StudentProfile` est créé, puis connexion automatique. |
| Connexion | `GET/POST /login` (`app_login`) | `SecurityController`, authentificateur `form_login` (CSRF activé : `enable_csrf: true`). |
| Déconnexion | `GET /logout` (`app_logout`) | Interceptée par le firewall (`logout.path: app_logout`). |

### Rôles et hiérarchie

Les rôles sont stockés sur `User` (colonne JSON `roles`) ; `getRoles()` garantit
toujours `ROLE_USER`. Hiérarchie (`config/packages/security.yaml`) :

```yaml
role_hierarchy:
    ROLE_STUDENT:   ROLE_USER
    ROLE_MODERATOR: ROLE_USER
    ROLE_ADMIN:     [ROLE_MODERATOR, ROLE_USER]
```

Ainsi un `ROLE_ADMIN` hérite des droits modérateur, et tout utilisateur connecté
possède `ROLE_USER`.

### Protection des pages privées

Deux niveaux de contrôle d'accès :

1. **Par URL** (`access_control`) :

   ```yaml
   access_control:
       - { path: ^/admin,      roles: ROLE_ADMIN }
       - { path: ^/moderation, roles: ROLE_MODERATOR }
       - { path: ^/review/new, roles: ROLE_USER }
   ```

2. **Par action** via l'attribut `#[IsGranted(...)]` (ex. `ModerationController`
   est protégé au niveau de la classe par `#[IsGranted('ROLE_MODERATOR')]` ; les
   actions favoris par `#[IsGranted('ROLE_USER')]`).

### Voter personnalisé — `ReviewVoter`

`src/Security/Voter/ReviewVoter.php` applique des règles au niveau de l'objet
`Review` (au-delà du simple rôle) :

| Attribut | Autorisé si |
|----------|-------------|
| `REVIEW_EDIT` | admin, **ou** auteur **et** avis encore en attente (`pending`) |
| `REVIEW_DELETE` | admin, **ou** auteur **et** avis encore `pending` |
| `REVIEW_APPROVE` / `REVIEW_REJECT` / `REVIEW_HIDE` | `ROLE_MODERATOR` (les admins en héritent) |

Un administrateur peut tout faire (court-circuit `isGranted('ROLE_ADMIN')`).
Le voter est câblé dans les contrôleurs via
`#[IsGranted(ReviewVoter::EDIT, subject: 'review')]`, etc.

### Favoris (cours)

Ajout/retrait d'un cours en favori (réservé aux utilisateurs connectés, protégé
par jeton CSRF, limité au propriétaire) :

| Action | Route |
|--------|-------|
| Ajouter / retirer (bascule) | `POST /favorite/course/{id}/toggle` (`app_favorite_toggle`) |
| Supprimer un favori précis | `POST /favorite/{id}/remove` (`app_favorite_remove`) |

### Modération automatique du contenu

À la soumission d'un avis, `ExternalModerationService` analyse le texte et
renvoie un verdict (`safe`, `aggressive`, `needs_review`) avec une éventuelle
reformulation. Un contenu `aggressive` est automatiquement rejeté. Le service
interroge une API externe si `EXTERNAL_MODERATION_URL` est défini, sinon une
analyse locale de secours est utilisée. Chaque appel est journalisé dans
`ApiLog`.

### Comptes de test (fixtures)

Tous les utilisateurs générés par les fixtures partagent le mot de passe
**`password`**. Répartition : **1 administrateur**, **3 modérateurs**,
**20 étudiants**. Les adresses e-mail étant générées aléatoirement, on peut les
lister avec :

```bash
php bin/console dbal:run-sql "SELECT email, roles FROM users ORDER BY roles"
```

## Installation rapide

```bash
composer install
docker compose up -d                     # PostgreSQL + Mailpit
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
symfony serve -d                         # ou: php -S 127.0.0.1:8000 -t public
```
