<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Entity\Formation;
use App\Entity\Review;
use App\Entity\Semester;
use App\Entity\User;
use App\Enum\PeriodType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminCourseControllerTest extends WebTestCase
{
    private function loginAdmin(KernelBrowser $client): User
    {
        $admin = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        static::assertNotNull($admin, 'A seeded ROLE_ADMIN user must exist');
        $client->loginUser($admin);

        return $admin;
    }

    public function testCoursesPageListsCoursesAndShowsDeleteButton(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $formation = (new Formation())->setName('Test Formation')->setPeriodType(PeriodType::Semester);
        $em->persist($formation);
        $semester = (new Semester())->setName('S1')->setNumber(1)->setFormation($formation);
        $em->persist($semester);
        $course = (new Course())->setTitle('Cours sans avis')->setSemester($semester)->setCreatedAt(new \DateTimeImmutable());
        $em->persist($course);
        $em->flush();
        $courseId = $course->getId();

        // Une vraie requête HTTP repartirait d'un EntityManager neuf ; on simule ça
        // ici pour éviter que la map d'identité ne serve les objets tout juste créés.
        $em->clear();

        $crawler = $client->request('GET', '/admin/courses');
        static::assertResponseIsSuccessful();
        static::assertStringContainsString('Cours sans avis', $client->getResponse()->getContent());
        static::assertCount(1, $crawler->filter('form[action="/admin/courses/' . $courseId . '/delete"]'));

        $em->remove($em->getRepository(Course::class)->find($courseId));
        $em->remove($em->getRepository(Semester::class)->find($semester->getId()));
        $em->remove($em->getRepository(Formation::class)->find($formation->getId()));
        $em->flush();
    }

    public function testDeletingACourseWithoutReviewsRemovesIt(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $formation = (new Formation())->setName('Test Formation 2')->setPeriodType(PeriodType::Semester);
        $em->persist($formation);
        $semester = (new Semester())->setName('S1')->setNumber(1)->setFormation($formation);
        $em->persist($semester);
        $course = (new Course())->setTitle('Cours à supprimer')->setSemester($semester)->setCreatedAt(new \DateTimeImmutable());
        $em->persist($course);
        $em->flush();
        $courseId = $course->getId();
        $semesterId = $semester->getId();
        $formationId = $formation->getId();
        $em->clear();

        $crawler = $client->request('GET', '/admin/courses');
        $token = $crawler->filter('form[action="/admin/courses/' . $courseId . '/delete"] input[name="_token"]')->attr('value');

        $client->request('POST', '/admin/courses/' . $courseId . '/delete', ['_token' => $token]);
        static::assertResponseRedirects('/admin/courses');

        static::assertNull($em->getRepository(Course::class)->find($courseId));

        $em->remove($em->getRepository(Semester::class)->find($semesterId));
        $em->remove($em->getRepository(Formation::class)->find($formationId));
        $em->flush();
    }

    public function testDeletingACourseWithReviewsIsBlocked(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $formation = (new Formation())->setName('Test Formation 3')->setPeriodType(PeriodType::Semester);
        $em->persist($formation);
        $semester = (new Semester())->setName('S1')->setNumber(1)->setFormation($formation);
        $em->persist($semester);
        $course = (new Course())->setTitle('Cours avec avis')->setSemester($semester)->setCreatedAt(new \DateTimeImmutable());
        $em->persist($course);

        $user = $em->getRepository(User::class)->findOneBy([]);
        $review = (new Review())
            ->setUser($user)
            ->setCourse($course)
            ->setTitle('Avis test')
            ->setContent('Contenu de test')
            ->setStatus(Review::STATUS_APPROVED)
            ->setCreatedAt(new \DateTimeImmutable());
        $em->persist($review);
        $em->flush();
        $courseId = $course->getId();
        $reviewId = $review->getId();
        $semesterId = $semester->getId();
        $formationId = $formation->getId();
        $em->clear();

        $crawler = $client->request('GET', '/admin/courses');
        static::assertCount(0, $crawler->filter('form[action="/admin/courses/' . $courseId . '/delete"]'), 'No delete form for a course with reviews: nothing in the UI can submit this request');

        // No token was ever issued for this course (its delete form never rendered), so this
        // POST can only fail — either at the CSRF check or the "has reviews" guard in the
        // controller. Either way, the course must survive.
        $client->request('POST', '/admin/courses/' . $courseId . '/delete', ['_token' => 'forged']);
        static::assertResponseRedirects('/admin/courses');
        static::assertNotNull($em->getRepository(Course::class)->find($courseId), 'Course with reviews must survive the delete attempt');

        $em->remove($em->getRepository(Review::class)->find($reviewId));
        $em->remove($em->getRepository(Course::class)->find($courseId));
        $em->remove($em->getRepository(Semester::class)->find($semesterId));
        $em->remove($em->getRepository(Formation::class)->find($formationId));
        $em->flush();
    }
}
