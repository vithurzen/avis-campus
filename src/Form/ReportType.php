<?php

namespace App\Form;

use App\Entity\Report;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reviewId', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('reason', ChoiceType::class, [
                'choices' => [
                    'Contenu inapproprié'  => 'Contenu inapproprié',
                    'Spam'                 => 'Spam',
                    'Propos offensants'    => 'Propos offensants',
                    'Hors sujet'           => 'Hors sujet',
                    'Fausse information'   => 'Fausse information',
                    'Autre'                => 'Autre',
                ],
                'placeholder'  => 'Choisir un motif…',
                'constraints'  => [new NotBlank(message: 'Veuillez choisir un motif.')],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr'     => [
                    'rows'        => 3,
                    'placeholder' => 'Précisions optionnelles…',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Report::class,
        ]);
    }
}
