<?php

namespace App\Form;

use App\Entity\Formation;
use App\Enum\PeriodType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('description')
            ->add('degreeLevel')
            ->add('periodType', EnumType::class, [
                'class'        => PeriodType::class,
                'choice_label' => fn(PeriodType $t) => $t->label(),
                'label'        => 'Type de période',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
        ]);
    }
}