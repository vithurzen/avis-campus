<?php

namespace App\Tests\Functional;

use App\Entity\Formation;
use App\Entity\Review;
use App\Entity\Semester;
use App\Enum\PeriodType;
use App\Repository\CourseRepository;
use App\Repository\FormationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end verification of the Formation → Semester → Course cascade
 * (Form Events PRE_SET_DATA / PRE_SUBMIT + JSON endpoints). Data-driven: it
 * discovers entity ids from the seeded DB rather than hardcoding them.
 */
final class ReviewCascadeTest extends WebTestCase
{
    private function login(KernelBrowser $client): object
    {
        $user = static::getContainer()->get(UserRepository::class)
            ->findOneBy(['email' => 'etudiant1@campus.fr']);
        static::assertNotNull($user, 'Seed user etudiant1@campus.fr must exist');
        $client->loginUser($user);

        return $user;
    }

    private function trimesterFormation(): Formation
    {
        $f = static::getContainer()->get(FormationRepository::class)
            ->findOneBy(['periodType' => PeriodType::Trimester]);
        static::assertNotNull($f, 'A trimester formation must be seeded');

        return $f;
    }

    public function testSemestersEndpointReturnsTrimesters(): void
    {
        $client = static::createClient();
        $this->login($client);
        $formation = $this->trimesterFormation();

        $client->request('GET', '/api/cascade/formations/' . $formation->getId() . '/semesters');
        static::assertResponseIsSuccessful();
        $names = array_column(json_decode($client->getResponse()->getContent(), true), 'name');
        static::assertSame(['T1', 'T2', 'T3'], $names, 'Trimester formation exposes T1/T2/T3');
    }

    public function testCoursesEndpointScopedToSemester(): void
    {
        $client = static::createClient();
        $user = $this->login($client);
        [$semester] = $this->semesterWithUnreviewedCourse($user);

        $client->request('GET', '/api/cascade/semesters/' . $semester->getId() . '/courses');
        static::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        static::assertNotEmpty($data, 'Semester exposes at least one course');
        static::assertArrayHasKey('title', $data[0]);
    }

    public function testNewReviewFormRendersCascadeSelects(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/review/new');
        static::assertResponseIsSuccessful();
        static::assertCount(1, $crawler->filter('select[name="review[formation]"]'), 'formation select present');
        static::assertCount(1, $crawler->filter('select[name="review[semester]"]'), 'semester select present');
        static::assertCount(1, $crawler->filter('select[name="review[course]"]'), 'course select present');
    }

    public function testPreSubmitAcceptsIdsNotInInitialDom(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = $this->login($client);

        [$semester, $course] = $this->semesterWithUnreviewedCourse($user);
        $formationId = $semester->getFormation()->getId();

        // GET the form to grab the CSRF token; semester/course selects are EMPTY in the DOM.
        $crawler = $client->request('GET', '/review/new');
        $token = $crawler->filter('input[name="review[_token]"]')->attr('value');

        // POST ids that were never rendered as <option>s — only PRE_SUBMIT makes them valid.
        $client->request('POST', '/review/new', ['review' => [
            'formation' => (string) $formationId,
            'semester'  => (string) $semester->getId(),
            'course'    => (string) $course->getId(),
            'title'     => 'Cascade test',
            'content'   => 'Verification du PRE_SUBMIT via la cascade.',
            'rating'    => '4',
            '_token'    => $token,
        ]]);

        static::assertResponseRedirects('/review');

        $em = $container->get(EntityManagerInterface::class);
        $review = $em->getRepository(Review::class)->findOneBy(['user' => $user, 'course' => $course]);
        static::assertNotNull($review, 'Review persisted with the submitted (non-DOM) course');

        // Clean up so the seeded DB stays consistent between runs.
        $em->remove($review);
        $em->flush();
    }

    /**
     * @return array{0: Semester, 1: \App\Entity\Course}
     */
    private function semesterWithUnreviewedCourse(object $user): array
    {
        $courseRepo = static::getContainer()->get(CourseRepository::class);
        foreach (static::getContainer()->get(EntityManagerInterface::class)
                     ->getRepository(Semester::class)->findAll() as $semester) {
            $courses = $courseRepo->findCoursesNotReviewedBy($user, null, $semester)->getQuery()->getResult();
            if ($courses !== []) {
                return [$semester, $courses[0]];
            }
        }
        static::markTestSkipped('No semester with an un-reviewed course for this user.');
    }
}
