<?php

namespace App\Tests\Controller;

use App\Entity\ApiLog;
use App\Entity\Course;
use App\Entity\Formation;
use App\Entity\Review;
use App\Entity\Semester;
use App\Entity\User;
use App\Enum\PeriodType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ReviewControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private ?int $userId = null;
    private ?int $courseId = null;
    private ?int $semesterId = null;
    private ?int $formationId = null;

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            // Les entités créées avant la requête HTTP sont détachées : l'EntityManager
            // est réinitialisé entre les requêtes en environnement de test. On les
            // recharge donc par id avant de les supprimer.
            if ($this->courseId !== null) {
                $review = $this->entityManager->getRepository(Review::class)->findOneBy(['course' => $this->courseId]);
                if ($review !== null) {
                    $this->entityManager->remove($review);
                }

                $course = $this->entityManager->find(Course::class, $this->courseId);
                if ($course !== null) {
                    $this->entityManager->remove($course);
                }
            }

            if ($this->semesterId !== null) {
                $semester = $this->entityManager->find(Semester::class, $this->semesterId);
                if ($semester !== null) {
                    $this->entityManager->remove($semester);
                }
            }

            if ($this->formationId !== null) {
                $formation = $this->entityManager->find(Formation::class, $this->formationId);
                if ($formation !== null) {
                    $this->entityManager->remove($formation);
                }
            }

            if ($this->userId !== null) {
                foreach ($this->entityManager->getRepository(ApiLog::class)->findBy(['user' => $this->userId]) as $apiLog) {
                    $this->entityManager->remove($apiLog);
                }

                $user = $this->entityManager->find(User::class, $this->userId);
                if ($user !== null) {
                    $this->entityManager->remove($user);
                }
            }

            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testAuthenticatedUserCanSubmitAReview(): void
    {
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $course = $this->createCourse();
        $this->courseId = $course->getId();
        $this->semesterId = $course->getSemester()?->getId();
        $this->formationId = $course->getSemester()?->getFormation()?->getId();

        $user = $this->createUser('etudiant@example.com');
        $this->userId = $user->getId();

        $client->loginUser($user);

        $crawler = $client->request('GET', '/review/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton("Soumettre l'avis")->form([
            'review[course]' => (string) $course->getId(),
            'review[title]' => 'Un cours vraiment intéressant',
            'review[content]' => 'Le professeur explique bien et les exercices sont utiles.',
            'review[rating]' => '5',
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/review');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $review = $this->entityManager->getRepository(Review::class)->findOneBy(['course' => $this->courseId]);

        self::assertNotNull($review);
        self::assertSame(Review::STATUS_APPROVED, $review->getStatus());
        self::assertSame($this->userId, $review->getUser()?->getId());
        self::assertSame(5, $review->getRating());
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/review/new');

        self::assertResponseRedirects('/login');
    }

    private function createCourse(): Course
    {
        $formation = new Formation();
        $formation->setName('Formation de test');
        $formation->setPeriodType(PeriodType::Semester);

        $semester = new Semester();
        $semester->setFormation($formation);
        $semester->setName('S1');
        $semester->setNumber(1);

        $course = new Course();
        $course->setSemester($semester);
        $course->setTitle('Algorithmique');

        $this->entityManager->persist($formation);
        $this->entityManager->persist($semester);
        $this->entityManager->persist($course);
        $this->entityManager->flush();

        return $course;
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setCreatedAt(new \DateTimeImmutable());

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'password'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
