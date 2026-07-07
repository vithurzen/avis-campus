**Cahier des charges**

1. **Presentation du projet**

**Nom du projet**  
Avis campus

**Type de projet**  
Application web développé avec symfony

**Description**  
Avis Campus est une plateforme web permettant aux étudiants de consulter, publier et modérer des avis sur les cours et unités d’enseignement de leur formation. L’objectif est d’aider les étudiants à mieux comprendre la difficulté, la charge de travail, l’utilité et l’organisation des différentes matières avant ou pendant leur parcours universitaire.

L’application ne vise pas à noter directement les enseignants, mais à proposer une plateforme d’échange constructif autour des cours. Les étudiants peuvent laisser des avis, attribuer des notes selon plusieurs critères, ajouter des cours en favoris et consulter des statistiques. Un système de modération permet de contrôler les contenus publiés afin d’éviter les abus.

**Objectif principal**  
L’objectif du projet est de concevoir une application web professionnelle, sécurisée et complète avec Symfony, en respectant les bonnes pratiques de développement : architecture claire, gestion des rôles, sécurité, base de données relationnelle, interface utilisateur soignée, API JSON, emails, tests, CI/CD et déploiement.

2. **Contexte et problématique**

Dans un parcours universitaire, les étudiants manquent souvent d’informations concrètes sur les cours qu’ils vont suivre. Les intitulés officiels ne suffisent pas toujours à comprendre la difficulté réelle, la charge de travail, les compétences nécessaires ou l’utilité pratique d’une matière.  
Les étudiants échangent souvent ces informations de manière informelle, par messages ou oralement. Cela rend les conseils difficiles à retrouver, peu organisés et parfois peu fiables.  
Avis Campus répond à ce besoin en centralisant les retours d’expérience des étudiants sur les cours, tout en gardant une approche respectueuse, modérée et orientée vers l’amélioration.

3. **Public cible**  
   

L’application vise principalement:

* les étudiants souhaitant consulter des avis sur les cours;  
* les étudiants souhaitant partager leur expérience;  
* les modérateurs chargés de vérifier les contenus publiés;  
* les administrateurs chargés de gérer la plateforme;  
* éventuellement les responsables pédagogiques souhaitant consulter les statistiques globales.

4. **Objectifs fonctionnels**

L’application doit permettre:

* la création d’un compte utilisateur;  
* la connexion et la déconnexion sécurisées;  
* la consultation des formations, semestres et cours;  
* la publication d’avis sur un cours;  
* l’ajout de commentaires sous un avis;  
* l’ajout de cours en favoris;  
* le signalement d’un avis inapproprié;  
* la modération des avis et commentaires;  
* la gestion des données depuis un espace administrateur;  
* l’envoi d’emails de notification;  
* la consultation de statistiques sur les cours;  
* l’utilisation d’une API externe pour assister la modération;

5. **Périmètre du projet**

**Fonctionnalités incluses:**

* espace étudiant  
* espace modérateur  
* espace administrateur  
* gestion des formations  
* gestion des semestres  
* gestion des cours  
* système d’avis  
* système de notation multicritère  
* Commentaires  
* Favoris  
* Signalements  
* notifications email  
* API JSON  
* consommation d’une API externe  
* dashboard administrateur  
* formulaires dynamiques  
* Fixtures  
* Tests  
* pipeline CI/CD  
* déploiement en ligne

**Fonctionnalités exclues:**

* la notation directe des professeurs  
* la publication d’informations personnelles sur les enseignants  
* un système de paiement  
* une messagerie privée complète  
* une application mobile  
* une authentification via Google ou autres réseaux sociaux

6. **Rôles utilisateurs**

L’application possède au minimum trois rôles principaux.

Etudiant \- ROLE\_USER  
L’étudiant est l’utilisateur principal de la plateforme.  
Il peut:

* créer un compte  
* se connecter  
* consulter les formations  
* consulter les semestres  
* consulter les cours  
* voir les avis associés à un cours  
* publier un avis  
* modifier ou supprimer ses propres avis selon certaines conditions  
* noter un cours selon plusieurs critères  
* commenter un avis  
* signaler un avis abusif  
* ajouter un cours en favori  
* consulter son profil  
* consulter ses propres avis  
* consulter ses favoris

Modérateur \- ROLE\_MODERATOR  
Le modérateur est chargé de contrôler les contenus publiés.  
Il peut:

* consulter les avis en attente  
* valider un avis  
* refuser un avis  
* masquer un avis publié  
* traiter les signalements  
* supprimer ou masquer un commentaire inapproprié  
* consulter l’historique des actions de modération  
* recevoir des notifications lorsqu’un contenu est signalé.

Administrateur \- ROLE\_ADMIN  
L’administrateur possède tous les droits sur la plateforme.  
Il peut :

* gérer les utilisateurs  
* gérer les formations  
* gérer les semestres  
* gérer les cours  
* gérer les tags  
* gérer les critères de notation  
* gérer les avis  
* gérer les commentaires  
* gérer les signalements  
* accéder aux statistiques globales  
* accéder à l’interface d’administration  
* consulter les logs  
* gérer les paramètres principaux de l’application

Responsable pédagogique \- ROLE\_MANAGER  
Il peut:

* consulter les statistiques d’une formation  
* consulter les tendances des avis  
* voir les cours les mieux notés  
* voir les cours considérés comme les plus difficiles  
* consulter les retours étudiants sans pouvoir modifier les avis.

7. **Cas d’utilisation principaux**

Inscription d’un étudiant

Acteur : étudiant  
Objectif : créer un compte sur la plateforme.

Scénario :

1. L’étudiant accède à la page d’inscription.  
2. Il remplit son email, mot de passe, prénom, nom et formation.  
3. Le système vérifie la validité des données.  
4. Le mot de passe est haché.  
5. Le compte est créé.  
6. Un email de confirmation est envoyé.  
7. L’étudiant peut ensuite se connecter.

Connexion d’un utilisateur

Acteur : étudiant, modérateur ou administrateur  
Objectif : accéder à son espace personnel.

Scénario :

1. L’utilisateur accède à la page de connexion.  
2. Il saisit son email et son mot de passe.  
3. Le système vérifie les identifiants.  
4. En cas de succès, l’utilisateur est redirigé vers son espace.  
5. En cas d’échec, un message d’erreur est affiché.

Consultation d’un cours

Acteur : étudiant  
Objectif : consulter les informations et avis d’un cours.

Scénario :

1. L’étudiant consulte la liste des formations.  
2. Il sélectionne une formation.  
3. Il choisit un semestre.  
4. Il accède à la liste des cours.  
5. Il ouvre la fiche d’un cours.  
6. Il voit la description, les notes moyennes, les avis et les ressources associées.

Publication d’un avis

Acteur : étudiant  
Objectif : donner un retour sur un cours.

Scénario :

1. L’étudiant accède à la fiche d’un cours.  
2. Il clique sur “Ajouter un avis”.  
3. Il remplit le formulaire.  
4. Il attribue des notes selon plusieurs critères.  
5. Il soumet l’avis.  
6. L’avis passe en statut “en attente de modération”.  
7. Un modérateur est notifié.  
8. L’étudiant reçoit un email lorsque son avis est validé ou refusé.

Modération d’un avis

Acteur : modérateur  
Objectif : valider ou refuser un avis.

Scénario :

1. Le modérateur accède à son tableau de modération.  
2. Il consulte les avis en attente.  
3. Il lit le contenu de l’avis.  
4. Il peut utiliser l’aide de l’API externe pour analyser le ton de l’avis.  
5. Il valide, refuse ou demande une modification.  
6. L’action est enregistrée.  
7. L’étudiant reçoit une notification par email.

Signalement d’un avis

Acteur : étudiant  
Objectif : signaler un contenu abusif.

Scénario :

1. L’étudiant consulte un avis.  
2. Il clique sur “Signaler”.  
3. Il choisit un motif.  
4. Le signalement est enregistré.  
5. Les modérateurs reçoivent une notification.  
6. Le signalement est traité depuis l’espace de modération.

Gestion administrateur

Acteur : administrateur  
Objectif : gérer les données globales du site.

Scénario :

1. L’administrateur se connecte.  
2. Il accède au dashboard admin.  
3. Il peut gérer les utilisateurs, formations, cours, avis, commentaires et signalements.  
4. Il peut consulter les statistiques globales.  
5. Il peut modifier ou supprimer des données si nécessaire.

