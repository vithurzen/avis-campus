<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Favorite;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/favorite')]
#[IsGranted('ROLE_USER')]
final class FavoriteController extends AbstractController
{
    #[Route('', name: 'app_favorite_index', methods: ['GET'])]
    public function index(FavoriteRepository $favorites): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('favorite/index.html.twig', [
            'favorites' => $favorites->findByUser($user),
        ]);
    }

    /**
     * Adds the course to the current user's favorites, or removes it if already
     * present (single endpoint covers both ajout and retrait).
     */
    #[Route('/course/{id}/toggle', name: 'app_favorite_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, Course $course, FavoriteRepository $favorites, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('favorite' . $course->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_course_show', ['id' => $course->getId()], Response::HTTP_SEE_OTHER);
        }

        /** @var User $user */
        $user = $this->getUser();
        $existing = $favorites->findOneByUserAndCourse($user, $course);

        if ($existing !== null) {
            $entityManager->remove($existing);
            $entityManager->flush();
            $this->addFlash('success', 'Cours retiré de vos favoris.');
        } else {
            $favorite = (new Favorite())
                ->setUser($user)
                ->setCourse($course);
            $entityManager->persist($favorite);
            $entityManager->flush();
            $this->addFlash('success', 'Cours ajouté à vos favoris.');
        }

        $redirect = $request->request->get('redirect', '');
        if ($redirect && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
            return $this->redirect($redirect, Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_course_show', ['id' => $course->getId()], Response::HTTP_SEE_OTHER);
    }

    /**
     * Removes a specific favorite (owner or admin only).
     */
    #[Route('/{id}/remove', name: 'app_favorite_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function remove(Request $request, Favorite $favorite, EntityManagerInterface $entityManager): Response
    {
        if ($favorite->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('unfavorite' . $favorite->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($favorite);
            $entityManager->flush();
            $this->addFlash('success', 'Favori supprimé.');
        }

        return $this->redirectToRoute('app_favorite_index', [], Response::HTTP_SEE_OTHER);
    }
}
