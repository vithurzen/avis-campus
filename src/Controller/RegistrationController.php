<?php

namespace App\Controller;

use App\Entity\StudentProfile;
use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        Security $security,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $now = new \DateTimeImmutable();

            $user
                ->setPassword($userPasswordHasher->hashPassword($user, $form->get('plainPassword')->getData()))
                ->setRoles(['ROLE_STUDENT'])
                ->setIsVerified(true)
                ->setCreatedAt($now);

            $profile = new StudentProfile();
            $profile
                ->setFirstName($form->get('firstName')->getData())
                ->setLastName($form->get('lastName')->getData())
                ->setCreatedAt($now);

            $user->setProfile($profile);

            $entityManager->persist($user);
            $entityManager->persist($profile);
            $entityManager->flush();

            // log the user in automatically on the main firewall
            $security->login($user, 'form_login', 'main');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
