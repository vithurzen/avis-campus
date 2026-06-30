Personne 1 — Backend, base de données et sécurité

Cette personne s’occupe du cœur technique du projet.

Missions principales
Créer le projet Symfony.
Configurer Doctrine et la base de données.
Créer les entités principales.
Mettre en place l’authentification.
Gérer les rôles utilisateurs.
Implémenter l’héritage Doctrine.
Créer les fixtures.
Mettre en place les Voters.
Entités à gérer

La personne 1 peut prendre en charge les entités suivantes :

User
StudentProfile
ModeratorProfile
AdminProfile
Formation
Semester
Course
Tag
Favorite
Fonctionnalités associées

Elle développe :

inscription ;
connexion ;
déconnexion ;
gestion des rôles : ROLE_USER, ROLE_MODERATOR, ROLE_ADMIN ;
relation entre étudiant et formation ;
ajout/retrait d’un cours en favori ;
sécurité des pages privées ;
ReviewVoter ou CourseVoter.
Livrables
Entités Doctrine.
Migrations.
Authentification Symfony.
Fixtures utilisateurs, formations, semestres, cours.
Voter personnalisé.
Partie sécurité du README.

Personne 2 — Fonctionnalités utilisateur et interface Twig

Cette personne s’occupe de toute la partie visible par les étudiants.

Missions principales
Créer les pages Twig côté utilisateur.
Développer la consultation des formations et cours.
Développer le système d’avis.
Développer les commentaires.
Développer les formulaires dynamiques.
Soigner l’UX/UI.
Entités à gérer

La personne 2 peut prendre en charge :

Review
ReviewRating
RatingCriteria
Comment
Resource
Notification
Pages à développer

Elle peut créer les pages suivantes :

Accueil
Liste des formations
Détail d’une formation
Liste des cours
Détail d’un cours
Formulaire de création d’avis
Mes avis
Mes favoris
Page profil étudiant
Classement des cours

Comme le cahier demande au moins 10 pages distinctes générées avec Twig, cette personne couvre une grosse partie de cette exigence.
Sujet (2).pdf

Formulaires dynamiques

Elle peut aussi gérer le formulaire dynamique principal :

Choix de la formation → chargement des semestres → chargement des cours associés

Exemple :

Formation : BUT Informatique
Semestre : S5
Cours : Symfony avancé

Le formulaire peut utiliser les événements Symfony PRE_SET_DATA et PRE_SUBMIT.

Livrables
Templates Twig.
Formulaires Symfony.
Contrôleurs utilisateur.
Pages de listing et détail.
Système d’avis, notes et commentaires.
Filtres Twig personnalisés si besoin.

Personne 3 — Administration, API, qualité, CI/CD et bonus

Cette personne s’occupe des fonctionnalités avancées et de la qualité du projet.

Missions principales
Développer l’espace d’administration.
Créer l’API JSON.
Intégrer une API externe.
Gérer l’envoi d’emails.
Mettre en place les tests.
Configurer la CI/CD.
Préparer le déploiement.
Gérer les éventuels bonus.
Entités à gérer

La personne 3 peut prendre en charge :

Report
EmailLog
ModerationAction
ApiLog
CourseStatistic
Interface d’administration

Elle développe l’espace admin avec EasyAdminBundle ou Twig.

L’admin doit pouvoir gérer :

utilisateurs ;
formations ;
semestres ;
cours ;
avis ;
commentaires ;
signalements ;
tags ;
statistiques.
API JSON

Elle crée des endpoints comme :

GET /api/v1/courses
GET /api/v1/courses/{id}
GET /api/v1/courses/{id}/reviews
GET /api/v1/formations/{id}/stats
GET /api/v1/top-courses

L’API doit utiliser le Serializer Symfony avec des groupes de normalisation, comme demandé dans le sujet.
Sujet (2).pdf

Emails

Elle met en place Symfony Mailer / Notifier pour :

confirmation d’inscription ;
notification quand un avis est validé ;
notification quand un avis est refusé ;
alerte modérateur quand un avis est signalé ;
récapitulatif hebdomadaire des meilleurs cours.
API externe

Pour ce sujet, l’API externe la plus adaptée est une API d’IA ou d’analyse de texte.

Exemple :

analyser si un avis est agressif ;
proposer une reformulation plus neutre ;
détecter automatiquement les avis inappropriés.
Tests et CI/CD

Elle peut gérer :

1 test unitaire ;
1 test fonctionnel avec WebTestCase ;
Symfony linter ;
PHPStan niveau 5 ;
PHPUnit ;
GitHub Actions ;
déploiement sur Render, Railway, VPS ou autre.

Le cahier demande justement une stratégie de tests, un pipeline CI fonctionnel et un déploiement accessible en ligne.
Sujet (2).pdf
