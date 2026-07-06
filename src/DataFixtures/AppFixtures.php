<?php

namespace App\DataFixtures;

use App\Entity\AdminProfile;
use App\Entity\Comment;
use App\Entity\Course;
use App\Entity\Formation;
use App\Entity\ModeratorProfile;
use App\Entity\Profile;
use App\Entity\RatingCriteria;
use App\Entity\Report;
use App\Entity\Review;
use App\Entity\ReviewRating;
use App\Entity\Semester;
use App\Entity\StudentProfile;
use App\Entity\Tag;
use App\Entity\Teacher;
use App\Entity\User;
use App\Enum\PeriodType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private Generator $faker;

    /** @var string[] */
    private array $bios = [
        'Étudiant passionné de développement web, toujours curieux d\'apprendre de nouvelles technologies.',
        'Passionné de cybersécurité, je participe régulièrement à des CTF en dehors des cours.',
        'Amateur de programmation, je code des petits projets perso sur mon temps libre.',
        'Intéressé par l\'intelligence artificielle et la data science depuis le lycée.',
        'Féru de cloud computing, je vise une carrière dans les infrastructures DevOps.',
        'Toujours partant pour un hackathon ou un projet open source le week-end.',
        'Curieux de tout ce qui touche à l\'architecture logicielle et aux bonnes pratiques de code.',
        'Ancien passionné de jeux vidéo, reconverti dans le développement par goût du code.',
    ];

    /** @var string[] */
    private array $reviewTitles = [
        'Excellent cours, je recommande !',
        'Très bon apprentissage',
        'Cours clair et bien structuré',
        'Une valeur sûre du programme',
        'Cours dense mais formateur',
        'Bon équilibre théorie/pratique',
        'Vraiment utile pour la suite',
        'Un des meilleurs cours du semestre',
        'Cours intéressant, quelques réserves',
        'Contenu solide, rythme soutenu',
        'Cours à revoir',
        'Décevant par rapport aux attentes',
        'Trop théorique à mon goût',
        'Manque de mise en pratique',
        'Difficile de suivre le rythme',
    ];

    /** @var string[] */
    private array $reviewSentences = [
        'Le cours « %s » est vraiment bien structuré, on progresse à un bon rythme.',
        'J\'ai beaucoup aimé les exercices pratiques proposés pendant « %s », ça aide à mieux comprendre la théorie.',
        'Le contenu de « %s » est dense mais l\'enseignant explique clairement les notions difficiles.',
        'Je recommande « %s » à ceux qui veulent renforcer leurs compétences, les TP sont bien pensés.',
        '« %s » manque un peu d\'exemples concrets, mais reste utile pour la suite du cursus.',
        'Cours exigeant, « %s » demande du travail personnel régulier pour bien suivre.',
        'Une bonne découverte avec « %s », le professeur est disponible et pédagogue.',
        '« %s » pourrait gagner en interactivité, certaines séances sont un peu longues.',
        'Très satisfait de « %s », les évaluations reflètent bien le contenu enseigné.',
        '« %s » est un bon complément aux autres matières du semestre.',
    ];

    /** @var string[] */
    private array $commentSentences = [
        'Je suis totalement d\'accord avec ce retour, très utile pour la suite.',
        'Merci pour ce partage, ça confirme ce que j\'avais ressenti aussi.',
        'Effectivement, il faut s\'accrocher mais ça vaut le coup.',
        'Je nuancerais un peu, le cours m\'a semblé plus accessible que ça.',
        'Bon résumé, j\'ajouterais que les ressources en ligne sont bien faites.',
        'Pareil pour moi, l\'enseignant est vraiment pédagogue.',
        'À prendre en compte avant de choisir ce cours en option.',
        'Un avis qui reflète bien la réalité du cours.',
        'Je recommande aussi de bien suivre dès le début du semestre.',
        'Perso j\'ai eu une expérience différente, plutôt positive.',
    ];

    public function __construct(private UserPasswordHasherInterface $hasher)
    {
        $this->faker = Factory::create('fr_FR');
    }

    public function load(ObjectManager $manager): void
    {
        // --- 2. Tags (×10) ---------------------------------------------------
        $tagNames = [
            'Algorithmique', 'Mathématiques', 'Réseaux', 'Web', 'Base de données',
            'Sécurité', 'IA', 'Systèmes', 'Gestion de projet', 'Anglais',
        ];
        $tags = [];
        foreach ($tagNames as $name) {
            $tag = (new Tag())
                ->setName($name)
                ->setColor($this->faker->hexColor());
            $manager->persist($tag);
            $tags[] = $tag;
        }

        // --- 3. Rating criteria (×5) ----------------------------------------
        $criteriaData = [
            'Clarté du cours' => 'Le cours est-il bien expliqué et facile à comprendre ?',
            'Pédagogie' => 'L\'enseignant transmet-il bien les connaissances ?',
            'Difficulté' => 'Le niveau de difficulté est-il adapté au niveau demandé ?',
            'Charge de travail' => 'La charge de travail demandée est-elle raisonnable ?',
            'Intérêt' => 'Le contenu du cours est-il intéressant et motivant ?',
        ];
        $criteria = [];
        foreach ($criteriaData as $name => $description) {
            $rc = (new RatingCriteria())
                ->setName($name)
                ->setDescription($description);
            $manager->persist($rc);
            $criteria[] = $rc;
        }

        // --- 4. Teachers (×5) -----------------------------------------------
        $teacherNames = [
            ['Marie', 'Dupont'], ['Jean', 'Martin'], ['Sophie', 'Bernard'],
            ['Karim', 'Haddad'], ['Camille', 'Girard'],
        ];
        $teachers = [];
        foreach ($teacherNames as [$firstName, $lastName]) {
            $teacher = (new Teacher())
                ->setFirstName($firstName)
                ->setLastName($lastName)
                ->setEmail(strtolower($firstName . '.' . $lastName . '@esgi.fr'))
                ->setCreatedAt($this->dt('-2 years'));
            $manager->persist($teacher);
            $teachers[] = $teacher;
        }

        // --- 5. Formations (×3, cursus ESGI) ----------------------------------
        $formationData = [
            ['Bachelor Informatique', 'Bac+3', PeriodType::Semester, 'Cycle Bachelor de l\'ESGI (3 ans) : socle généraliste en programmation, algorithmique, réseaux et bases de données.'],
            ['Mastère Intelligence Artificielle & Big Data', 'Bac+5', PeriodType::Semester, 'Spécialisation de cycle ingénieur ESGI (Bac+5) en science des données, machine learning et traitement de données massives.'],
            ['Mastère Cybersécurité & Ethical Hacking', 'Bac+5', PeriodType::Trimester, 'Spécialisation de cycle ingénieur ESGI (Bac+5) dédiée à la sécurité offensive et défensive des systèmes d\'information.'],
        ];
        $formations = [];
        foreach ($formationData as [$name, $level, $periodType, $description]) {
            $formation = (new Formation())
                ->setName($name)
                ->setDegreeLevel($level)
                ->setPeriodType($periodType)
                ->setDescription($description)
                ->setCreatedAt($this->dt('-2 years'));
            $manager->persist($formation);
            $formations[] = $formation;
        }

        // --- 6. Periods (2 par formation semestrielle, 3 par trimestrielle) --
        $semesters = [];
        foreach ($formations as $formation) {
            $abbr = $formation->getPeriodType()->abbreviation();
            $periodCount = $formation->getPeriodType() === PeriodType::Trimester ? 3 : 2;
            for ($n = 1; $n <= $periodCount; $n++) {
                $semester = (new Semester())
                    ->setFormation($formation)
                    ->setName($abbr . $n)
                    ->setNumber($n);
                $manager->persist($semester);
                $semesters[] = $semester;
            }
        }

        // --- 7. Courses (×20, intitulés réels ESGI) --------------------------
        $courseData = [
            ['Algorithmique et Programmation', 'Bases de la programmation procédurale en Python et C, structures de contrôle et premiers algorithmes.'],
            ['Bases de Données Relationnelles', 'Modélisation entité-association, langage SQL et administration d\'une base PostgreSQL.'],
            ['Programmation Orientée Objet en Java', 'Concepts de la POO (héritage, polymorphisme, interfaces) appliqués en Java.'],
            ['Réseaux Informatiques', 'Modèle OSI/TCP-IP, adressage, routage et travaux pratiques de configuration réseau.'],
            ['Systèmes d\'Exploitation', 'Fonctionnement des systèmes Unix/Linux, gestion des processus, de la mémoire et des fichiers.'],
            ['Structures de Données', 'Listes, piles, files, arbres et graphes : implémentation et complexité.'],
            ['Mathématiques Discrètes', 'Logique, théorie des ensembles, combinatoire et théorie des graphes appliquées à l\'informatique.'],
            ['Anglais Technique', 'Vocabulaire informatique, rédaction de documentation et communication professionnelle en anglais.'],
            ['Machine Learning', 'Apprentissage supervisé et non supervisé : régression, classification, clustering.'],
            ['Deep Learning et Réseaux de Neurones', 'Réseaux de neurones, CNN, RNN et frameworks PyTorch/TensorFlow.'],
            ['Big Data et Traitement de Données Massives', 'Écosystème Hadoop/Spark, pipelines de données distribués et stockage NoSQL.'],
            ['Traitement du Langage Naturel (NLP)', 'Modèles de langage, embeddings et applications de NLP modernes.'],
            ['Data Visualization', 'Conception de tableaux de bord et visualisations de données pertinentes.'],
            ['Ethical Hacking et Tests d\'Intrusion', 'Méthodologie du pentest, reconnaissance, exploitation de vulnérabilités.'],
            ['Cryptographie Appliquée', 'Chiffrement symétrique/asymétrique, PKI et gestion des incidents de sécurité.'],
            ['Sécurité des Systèmes et Réseaux', 'Durcissement des systèmes, détection d\'intrusion et gouvernance SSI.'],
            ['Analyse de Malwares et Forensics', 'Rétro-ingénierie de logiciels malveillants et investigation numérique.'],
            ['Gestion de Projet Agile', 'Méthodologies Scrum et Kanban appliquées à la conduite de projets informatiques.'],
            ['Architecture Logicielle', 'Architectures microservices, patrons de conception et principes SOLID.'],
            ['Conteneurisation avec Docker', 'Conception d\'images Docker et déploiement d\'applications conteneurisées.'],
        ];
        $courses = [];
        foreach ($courseData as $i => [$title, $description]) {
            $course = (new Course())
                ->setSemester($this->faker->randomElement($semesters))
                ->setTitle($title)
                ->setDescription($description)
                ->setCoefficient($this->faker->randomFloat(1, 1, 3))
                ->setHours($this->faker->numberBetween(20, 50))
                ->setCreatedAt($this->dt('-1 year'));

            foreach ($this->pick($teachers, $this->faker->numberBetween(1, 2)) as $teacher) {
                $course->addTeacher($teacher);
            }
            foreach ($this->pick($tags, $this->faker->numberBetween(1, 3)) as $tag) {
                $course->addTag($tag);
            }

            $manager->persist($course);
            $courses[] = $course;
        }

        // --- 8. Users + profiles --------------------------------------------
        // 2 admins
        $adminProfile = (new AdminProfile())
            ->setAdminCode($this->faker->bothify('ADM-####'))
            ->setSuperAdmin(true);
        $this->makeUser($manager, ['ROLE_ADMIN'], $adminProfile);

        $admin2Profile = (new AdminProfile())
            ->setAdminCode('ADMIN002')
            ->setSuperAdmin(true);
        $this->makeUser($manager, ['ROLE_ADMIN'], $admin2Profile, 'admin@admin.fr', '$2y$13$qboDSH6Q8IaYb/Y/2OO4zOmb7aQb35gkeRB2qRuM2vVZhFbiTmnSu');

        // 3 moderators
        for ($i = 0; $i < 3; $i++) {
            $modProfile = (new ModeratorProfile())
                ->setModerationArea($this->faker->randomElement(['Avis', 'Commentaires', 'Signalements']));
            $this->makeUser($manager, ['ROLE_MODERATOR'], $modProfile);
        }

        // 20 students (le premier a un e-mail déterministe, utilisé par les tests)
        $students = [];
        for ($i = 0; $i < 20; $i++) {
            $studentProfile = (new StudentProfile())
                ->setFormation($this->faker->randomElement($formations))
                ->setLevel($this->faker->randomElement(['Bachelor 1', 'Bachelor 2', 'Bachelor 3', 'Mastère 1', 'Mastère 2']))
                ->setCurrentYear($this->faker->numberBetween(1, 5));
            $email = 0 === $i ? 'etudiant1@campus.fr' : null;
            $students[] = $this->makeUser($manager, ['ROLE_STUDENT'], $studentProfile, $email);
        }

        // --- 9. Reviews (×50, unique student×course pairs) ------------------
        $pairs = [];
        foreach ($students as $student) {
            foreach ($courses as $course) {
                $pairs[] = [$student, $course];
            }
        }
        shuffle($pairs);
        $pairs = array_slice($pairs, 0, 50);

        $reviews = [];
        foreach ($pairs as [$student, $course]) {
            $s1 = sprintf($this->faker->randomElement($this->reviewSentences), $course->getTitle());
            $s2 = sprintf($this->faker->randomElement($this->reviewSentences), $course->getTitle());
            $review = (new Review())
                ->setUser($student)
                ->setCourse($course)
                ->setTitle($this->faker->randomElement($this->reviewTitles))
                ->setContent($s1 . ' ' . $s2)
                ->setStatus($this->faker->randomElement([
                    Review::STATUS_APPROVED,
                    Review::STATUS_APPROVED,
                    Review::STATUS_APPROVED,
                    Review::STATUS_HIDDEN,
                ]))
                ->setRating($this->faker->optional(0.85)->numberBetween(1, 5))
                ->setCreatedAt($this->dt('-6 months'));
            $manager->persist($review);
            $reviews[] = $review;
        }

        // --- 10. Review ratings (×100: 2 distinct criteria per review) -------
        foreach ($reviews as $review) {
            foreach ($this->pick($criteria, 2) as $rc) {
                $rating = (new ReviewRating())
                    ->setReview($review)
                    ->setRatingCriteria($rc)
                    ->setScore($this->faker->numberBetween(1, $rc->getMaxScore()));
                $manager->persist($rating);
            }
        }

        // --- 11. Comments (×30) ---------------------------------------------
        $comments = [];
        for ($i = 0; $i < 30; $i++) {
            $comment = (new Comment())
                ->setUser($this->faker->randomElement($students))
                ->setReview($this->faker->randomElement($reviews))
                ->setContent($this->faker->randomElement($this->commentSentences))
                ->setCreatedAt($this->dt('-3 months'));
            $manager->persist($comment);
            $comments[] = $comment;
        }

        // --- 12. Reports (×10) ----------------------------------------------
        $reasons = ['Contenu inapproprié', 'Spam', 'Propos offensants', 'Hors sujet', 'Fausse information'];
        $reasonDescriptions = [
            'Contenu inapproprié' => 'Cet avis contient des propos qui ne respectent pas les règles de la plateforme.',
            'Spam' => 'Ce contenu semble avoir été copié d\'un autre site, sans intérêt original.',
            'Propos offensants' => 'Le ton employé est irrespectueux envers l\'équipe pédagogique.',
            'Hors sujet' => 'Ce contenu ne parle pas du tout du cours concerné.',
            'Fausse information' => 'Les informations données sont manifestement inexactes.',
        ];
        for ($i = 0; $i < 10; $i++) {
            $reason = $this->faker->randomElement($reasons);
            $report = (new Report())
                ->setUser($this->faker->randomElement($students))
                ->setReason($reason)
                ->setDescription($reasonDescriptions[$reason])
                ->setCreatedAt($this->dt('-2 months'));

            if ($this->faker->boolean()) {
                $report->setReview($this->faker->randomElement($reviews));
            } else {
                $report->setComment($this->faker->randomElement($comments));
            }
            $manager->persist($report);
        }

        $manager->flush();
    }

    /**
     * Create a User with a hashed password and link it to its profile.
     */
    private function makeUser(ObjectManager $manager, array $roles, Profile $profile, ?string $email = null, ?string $rawPasswordHash = null): User
    {
        $user = new User();
        $user->setEmail($email ?? $this->faker->unique()->safeEmail())
            ->setRoles($roles)
            ->setPassword($rawPasswordHash ?? $this->hasher->hashPassword($user, 'password'))
            ->setIsVerified(true)
            ->setCreatedAt($this->dt('-1 year'));

        $profile->setFirstName($this->faker->firstName())
            ->setLastName($this->faker->lastName())
            ->setBio($this->faker->randomElement($this->bios))
            ->setCreatedAt($user->getCreatedAt());

        $user->setProfile($profile);

        $manager->persist($user);
        $manager->persist($profile);

        return $user;
    }

    /**
     * Build an immutable date between $start and now (Faker returns mutable DateTime).
     */
    private function dt(string $start = '-1 year'): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween($start, 'now'));
    }

    /**
     * Pick $count distinct random elements from $items.
     *
     * @template T
     * @param array<int, T> $items
     * @return array<int, T>
     */
    private function pick(array $items, int $count): array
    {
        $count = min($count, count($items));

        return (array) $this->faker->randomElements($items, $count);
    }
}
