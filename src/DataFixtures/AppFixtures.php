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
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private Generator $faker;

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
        $criteriaNames = [
            'Clarté du cours', 'Pédagogie', 'Difficulté', 'Charge de travail', 'Intérêt',
        ];
        $criteria = [];
        foreach ($criteriaNames as $name) {
            $rc = (new RatingCriteria())
                ->setName($name)
                ->setDescription($this->faker->optional()->sentence());
            $manager->persist($rc);
            $criteria[] = $rc;
        }

        // --- 4. Teachers (×5) -----------------------------------------------
        $teachers = [];
        for ($i = 0; $i < 5; $i++) {
            $teacher = (new Teacher())
                ->setFirstName($this->faker->firstName())
                ->setLastName($this->faker->lastName())
                ->setEmail($this->faker->unique()->safeEmail())
                ->setCreatedAt($this->dt('-2 years'));
            $manager->persist($teacher);
            $teachers[] = $teacher;
        }

        // --- 5. Formations (×3) ---------------------------------------------
        $formationData = [
            ['Master Informatique', 'Master'],
            ['Licence Mathématiques', 'Licence'],
            ['Master Cybersécurité', 'Master'],
        ];
        $formations = [];
        foreach ($formationData as [$name, $level]) {
            $formation = (new Formation())
                ->setName($name)
                ->setDegreeLevel($level)
                ->setDescription($this->faker->paragraph())
                ->setCreatedAt($this->dt('-2 years'));
            $manager->persist($formation);
            $formations[] = $formation;
        }

        // --- 6. Semesters (×6: 2 per formation) -----------------------------
        $semesters = [];
        $semesterNumber = 1;
        foreach ($formations as $formation) {
            for ($s = 1; $s <= 2; $s++) {
                $semester = (new Semester())
                    ->setFormation($formation)
                    ->setName('Semestre ' . $semesterNumber)
                    ->setNumber($semesterNumber);
                $manager->persist($semester);
                $semesters[] = $semester;
                $semesterNumber++;
            }
        }

        // --- 7. Courses (×20) -----------------------------------------------
        $courses = [];
        for ($i = 0; $i < 20; $i++) {
            $course = (new Course())
                ->setSemester($this->faker->randomElement($semesters))
                ->setTitle(ucfirst($this->faker->words(3, true)))
                ->setDescription($this->faker->paragraph())
                ->setCoefficient($this->faker->randomFloat(1, 1, 5))
                ->setHours($this->faker->numberBetween(10, 60))
                ->setCreatedAt($this->dt('-1 year'));

            foreach ($this->pick($teachers, $this->faker->numberBetween(1, 3)) as $teacher) {
                $course->addTeacher($teacher);
            }
            foreach ($this->pick($tags, $this->faker->numberBetween(2, 4)) as $tag) {
                $course->addTag($tag);
            }

            $manager->persist($course);
            $courses[] = $course;
        }

        // --- 8. Users + profiles --------------------------------------------
        // 1 admin
        $adminProfile = (new AdminProfile())
            ->setAdminCode($this->faker->bothify('ADM-####'))
            ->setSuperAdmin(true);
        $this->makeUser($manager, ['ROLE_ADMIN'], $adminProfile);

        // 3 moderators
        for ($i = 0; $i < 3; $i++) {
            $modProfile = (new ModeratorProfile())
                ->setModerationArea($this->faker->randomElement(['Avis', 'Commentaires', 'Signalements']));
            $this->makeUser($manager, ['ROLE_MODERATOR'], $modProfile);
        }

        // 20 students
        $students = [];
        for ($i = 0; $i < 20; $i++) {
            $studentProfile = (new StudentProfile())
                ->setFormation($this->faker->randomElement($formations))
                ->setLevel($this->faker->randomElement(['L1', 'L2', 'L3', 'M1', 'M2']))
                ->setCurrentYear($this->faker->numberBetween(1, 5));
            $students[] = $this->makeUser($manager, ['ROLE_STUDENT'], $studentProfile);
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
            $review = (new Review())
                ->setUser($student)
                ->setCourse($course)
                ->setTitle($this->faker->sentence(4))
                ->setContent($this->faker->paragraphs(2, true))
                ->setStatus($this->faker->randomElement(['approved', 'approved', 'pending', 'rejected']))
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
                ->setContent($this->faker->paragraph())
                ->setCreatedAt($this->dt('-3 months'));
            $manager->persist($comment);
            $comments[] = $comment;
        }

        // --- 12. Reports (×10) ----------------------------------------------
        $reasons = ['Contenu inapproprié', 'Spam', 'Propos offensants', 'Hors sujet', 'Fausse information'];
        for ($i = 0; $i < 10; $i++) {
            $report = (new Report())
                ->setUser($this->faker->randomElement($students))
                ->setReason($this->faker->randomElement($reasons))
                ->setDescription($this->faker->optional()->sentence())
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
    private function makeUser(ObjectManager $manager, array $roles, Profile $profile): User
    {
        $user = new User();
        $user->setEmail($this->faker->unique()->safeEmail())
            ->setRoles($roles)
            ->setPassword($this->hasher->hashPassword($user, 'password'))
            ->setIsVerified(true)
            ->setCreatedAt($this->dt('-1 year'));

        $profile->setFirstName($this->faker->firstName())
            ->setLastName($this->faker->lastName())
            ->setBio($this->faker->optional()->paragraph())
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
