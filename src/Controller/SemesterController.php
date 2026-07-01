<?php

namespace App\Controller;

use App\Entity\Semester;
use App\Form\SemesterType;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/semester')]
final class SemesterController extends AbstractController
{
    #[Route('/new', name: 'app_semester_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager, FormationRepository $formationRepository): Response
    {
        $semester = new Semester();

        $formation = null;
        if ($formationId = $request->query->get('formation')) {
            $formation = $formationRepository->find($formationId);
            if ($formation) {
                $semester->setFormation($formation);
            }
        }

        $form = $this->createForm(SemesterType::class, $semester, [
            'show_formation' => $formation === null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($semester);
            $entityManager->flush();

            if ($formation !== null) {
                $this->addFlash('success', 'Semestre ajouté. Ajoutez-en un autre ou terminez.');
                return $this->redirectToRoute('app_semester_new', ['formation' => $formation->getId()]);
            }

            return $this->redirectToRoute('app_formation_show', ['id' => $semester->getFormation()->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('semester/new.html.twig', [
            'semester' => $semester,
            'form' => $form,
            'formation' => $formation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_semester_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Semester $semester, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SemesterType::class, $semester);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_formation_show', ['id' => $semester->getFormation()->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('semester/edit.html.twig', [
            'semester' => $semester,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_semester_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Semester $semester, EntityManagerInterface $entityManager): Response
    {
        $formationId = $semester->getFormation()->getId();

        if ($this->isCsrfTokenValid('delete'.$semester->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($semester);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_formation_show', ['id' => $formationId], Response::HTTP_SEE_OTHER);
    }
}
