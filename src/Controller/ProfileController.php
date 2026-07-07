<?php

namespace App\Controller;

use App\Form\ProfileType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    #[Route('/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $user    = $this->getUser();
        $profile = $user->getProfile();

        $profileForm = $this->createForm(ProfileType::class, $profile);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $profile->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_profile_edit', [], Response::HTTP_SEE_OTHER);
        }

        $inputClass = 'w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand bg-white';

        $emailForm = $this->createFormBuilder(null, ['attr' => ['id' => 'email-form']])
            ->add('email', EmailType::class, [
                'label'       => false,
                'data'        => $user->getEmail(),
                'constraints' => [
                    new NotBlank(message: 'L\'e-mail est requis.'),
                    new Email(message: 'Adresse e-mail invalide.'),
                ],
                'attr' => ['class' => $inputClass],
            ])
            ->add('currentPassword', PasswordType::class, [
                'label'       => false,
                'constraints' => [new NotBlank(message: 'Le mot de passe actuel est requis.')],
                'attr'        => ['class' => $inputClass],
            ])
            ->getForm();

        $emailForm->handleRequest($request);

        if ($emailForm->isSubmitted() && $emailForm->isValid()) {
            $data     = $emailForm->getData();
            $newEmail = $data['email'];

            if (!$hasher->isPasswordValid($user, $data['currentPassword'])) {
                $this->addFlash('error', 'Mot de passe incorrect.');

                return $this->redirectToRoute('app_profile_edit', [], Response::HTTP_SEE_OTHER);
            }

            if ($newEmail !== $user->getEmail() && $userRepository->findOneBy(['email' => $newEmail])) {
                $this->addFlash('error', 'Cette adresse e-mail est déjà utilisée.');

                return $this->redirectToRoute('app_profile_edit', [], Response::HTTP_SEE_OTHER);
            }

            $user->setEmail($newEmail);
            $entityManager->flush();

            $this->addFlash('success', 'Adresse e-mail mise à jour.');

            return $this->redirectToRoute('app_profile_edit', [], Response::HTTP_SEE_OTHER);
        }

        $passwordForm = $this->createFormBuilder(null, ['attr' => ['id' => 'password-form']])
            ->add('currentPassword', PasswordType::class, [
                'label'       => false,
                'constraints' => [new NotBlank(message: 'Le mot de passe actuel est requis.')],
                'attr'        => ['class' => $inputClass],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'first_options'   => [
                    'label' => false,
                    'attr'  => ['class' => $inputClass, 'placeholder' => ''],
                ],
                'second_options'  => [
                    'label' => false,
                    'attr'  => ['class' => $inputClass, 'placeholder' => ''],
                ],
                'constraints' => [
                    new NotBlank(message: 'Le nouveau mot de passe est requis.'),
                    new Length(min: 8, minMessage: 'Minimum 8 caractères.'),
                ],
            ])
            ->getForm();

        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $data = $passwordForm->getData();

            if (!$hasher->isPasswordValid($user, $data['currentPassword'])) {
                $this->addFlash('error', 'Mot de passe actuel incorrect.');

                return $this->redirectToRoute('app_profile_edit', [], Response::HTTP_SEE_OTHER);
            }

            $user->setPassword($hasher->hashPassword($user, $data['newPassword']));
            $entityManager->flush();

            $this->addFlash('success', 'Mot de passe mis à jour.');

            return $this->redirectToRoute('app_profile_edit', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('profile/edit.html.twig', [
            'user'         => $user,
            'profile'      => $profile,
            'form'         => $profileForm,
            'emailForm'    => $emailForm,
            'passwordForm' => $passwordForm,
        ]);
    }
}
