<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\ForgotPasswordRequestType;
use App\Repository\EmailLogRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ResetPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        UserRepository $userRepository,
        EmailLogRepository $emailLogRepository,
        EmailService $emailService,
        UriSigner $uriSigner,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        $form = $this->createForm(ForgotPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            $user = $userRepository->findOneBy(['email' => $email]);

            // Anti-abuse: skip if a reset email was already sent to this address recently.
            $throttled = $emailLogRepository->hasRecentByType(
                $email,
                'password_reset',
                new \DateTimeImmutable('-60 seconds'),
            );

            if ($user !== null && !$throttled) {
                $resetUrl = $uriSigner->sign(
                    $urlGenerator->generate(
                        'app_reset_password',
                        ['id' => $user->getId(), 'token' => self::selector($user)],
                        UrlGeneratorInterface::ABSOLUTE_URL,
                    ),
                    new \DateTimeImmutable('+1 hour'),
                );

                $emailService->sendTemplate(
                    $email,
                    'Réinitialisation de votre mot de passe',
                    'emails/password_reset.html.twig',
                    ['user' => $user, 'profile' => $user->getProfile(), 'resetUrl' => $resetUrl],
                    'password_reset',
                    $user,
                );
            }

            // Always identical response — do not reveal whether the account exists.
            $this->addFlash(
                'success',
                'Si un compte est associé à cette adresse, un email de réinitialisation vient d\'être envoyé.',
            );

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password_request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    #[Route('/reset-password/{id}', name: 'app_reset_password', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function reset(
        int $id,
        Request $request,
        UriSigner $uriSigner,
        UserRepository $userRepository,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$uriSigner->checkRequest($request)) {
            $this->addFlash('error', 'Ce lien de réinitialisation est invalide ou a expiré.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $user = $userRepository->find($id);
        if (!$user || !hash_equals(self::selector($user), (string) $request->query->get('token'))) {
            // Selector mismatch = link already used (password changed) or tampered.
            $this->addFlash('error', 'Ce lien de réinitialisation n\'est plus valide. Veuillez en demander un nouveau.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user
                ->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()))
                ->setIsVerified(true) // clicking the emailed link proves email ownership
                ->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form,
        ]);
    }

    /**
     * Selector bound to the current password hash: once the password changes, every
     * previously issued reset link stops validating (single-use links).
     */
    private static function selector(User $user): string
    {
        return substr(hash('sha256', $user->getPassword().'|'.$user->getId()), 0, 24);
    }
}
