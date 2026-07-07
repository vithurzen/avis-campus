<?php

namespace App\Command;

use App\Form\ProfileType;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Twig\Environment;

#[AsCommand(name: 'app:tmp-profile-test')]
class TmpProfileTestCommand extends Command
{
    public function __construct(
        private Environment $twig,
        private FormFactoryInterface $formFactory,
        private RequestStack $requestStack,
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $request = Request::create('/profile/edit');
        $request->attributes->set('_route', 'app_profile_edit');
        $this->requestStack->push($request);

        foreach (['etudiant1@campus.fr' => 'student', 'admin@campus.fr' => 'admin'] as $email => $label) {
            $user = $this->userRepository->findOneBy(['email' => $email]);
            if (!$user) {
                $output->writeln("$label: user not found ($email)");
                continue;
            }
            $profile = $user->getProfile();

            $profileForm = $this->formFactory->create(ProfileType::class, $profile);

            $inputClass = 'w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5';
            $emailForm = $this->formFactory->createNamedBuilder('', FormType::class, null, ['attr' => ['id' => 'email-form']])
                ->add('email', EmailType::class, [
                    'label' => false,
                    'data' => $user->getEmail(),
                    'constraints' => [new NotBlank(), new Email()],
                    'attr' => ['class' => $inputClass],
                ])
                ->add('currentPassword', PasswordType::class, [
                    'label' => false,
                    'constraints' => [new NotBlank()],
                    'attr' => ['class' => $inputClass],
                ])
                ->getForm();

            $passwordForm = $this->formFactory->createNamedBuilder('', FormType::class, null, ['attr' => ['id' => 'password-form']])
                ->add('currentPassword', PasswordType::class, [
                    'label' => false,
                    'constraints' => [new NotBlank()],
                    'attr' => ['class' => $inputClass],
                ])
                ->add('newPassword', RepeatedType::class, [
                    'type' => PasswordType::class,
                    'first_options' => ['label' => false, 'attr' => ['class' => $inputClass]],
                    'second_options' => ['label' => false, 'attr' => ['class' => $inputClass]],
                    'constraints' => [new NotBlank(), new Length(min: 8)],
                ])
                ->getForm();

            $content = $this->twig->render('profile/edit.html.twig', [
                'user' => $user,
                'profile' => $profile,
                'form' => $profileForm->createView(),
                'emailForm' => $emailForm->createView(),
                'passwordForm' => $passwordForm->createView(),
            ]);

            $output->writeln("$label: rendered " . strlen($content) . ' bytes');
            $output->writeln('  has "Niveau": ' . (str_contains($content, 'Niveau') ? 'YES' : 'no'));
            $output->writeln('  has user email: ' . (str_contains($content, $user->getEmail()) ? 'YES' : 'no'));
        }

        return Command::SUCCESS;
    }
}
