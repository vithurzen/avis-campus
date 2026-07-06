<?php

namespace App\Controller;

use App\Entity\StudentProfile;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        EmailService $emailService,
        UriSigner $uriSigner,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $now = new \DateTimeImmutable();

            $user
                ->setPassword($userPasswordHasher->hashPassword($user, $form->get('plainPassword')->getData()))
                ->setRoles(['ROLE_STUDENT'])
                ->setIsVerified(false)
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

            // Build a signed, time-limited link the user must click to activate the account.
            $confirmUrl = $uriSigner->sign(
                $urlGenerator->generate(
                    'app_verify_email',
                    ['id' => $user->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                new \DateTimeImmutable('+1 hour'),
            );

            $emailService->sendTemplate(
                $user->getEmail(),
                'Confirmez votre inscription sur Avis Campus',
                'emails/registration_confirmation.html.twig',
                ['user' => $user, 'profile' => $profile, 'confirmUrl' => $confirmUrl],
                'registration_confirmation',
                $user,
            );

            $this->addFlash(
                'success',
                'Un email de confirmation vient de vous être envoyé. Cliquez sur le lien qu\'il contient pour activer votre compte.',
            );

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify-email/{id}', name: 'app_verify_email', requirements: ['id' => '\d+'])]
    public function verifyEmail(
        int $id,
        Request $request,
        UriSigner $uriSigner,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        Security $security,
        EmailService $emailService,
    ): Response {
        if (!$uriSigner->checkRequest($request)) {
            $this->addFlash('error', 'Ce lien de confirmation est invalide ou a expiré.');

            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->find($id);
        if (!$user) {
            $this->addFlash('error', 'Compte introuvable.');

            return $this->redirectToRoute('app_login');
        }

        if (!$user->isVerified()) {
            $user
                ->setIsVerified(true)
                ->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            // Welcome email now that the account is actually active.
            $emailService->sendTemplate(
                $user->getEmail(),
                'Bienvenue sur Avis Campus',
                'emails/registration_welcome.html.twig',
                ['user' => $user, 'profile' => $user->getProfile()],
                'registration_welcome',
                $user,
            );
        }

        // isVerified is now true, so the UserChecker allows this login.
        $security->login($user, 'form_login', 'main');

        return $this->redirectToRoute('app_home');
    }
}
