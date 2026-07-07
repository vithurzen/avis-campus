<?php

namespace App\Form;

use App\Entity\Profile;
use App\Entity\StudentProfile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label'       => false,
                'constraints' => [
                    new NotBlank(message: 'Le prénom est requis.'),
                    new Length(max: 100),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label'       => false,
                'constraints' => [
                    new NotBlank(message: 'Le nom est requis.'),
                    new Length(max: 100),
                ],
            ])
            ->add('bio', TextareaType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => ['rows' => 4],
            ])
        ;

        if ($options['data'] instanceof StudentProfile) {
            $builder->add('level', TextType::class, [
                'label'    => false,
                'required' => false,
                'constraints' => [new Length(max: 100)],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Profile::class]);
    }
}
