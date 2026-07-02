<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\Review;
use App\Repository\CourseRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReviewType extends AbstractType
{
    public function __construct(
        private readonly Security $security,
        private readonly CourseRepository $courseRepository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentUser = $this->security->getUser();
        $currentCourse = $builder->getData()?->getCourse();

        $builder
            ->add('title')
            ->add('content')
            ->add('course', EntityType::class, [
                'class' => Course::class,
                'choice_label' => 'title',
                'query_builder' => $currentUser
                    ? $this->courseRepository->findCoursesNotReviewedBy($currentUser, $currentCourse)
                    : null,
            ])
            ->add('rating', IntegerType::class, [
                'required' => false,
                'attr'     => ['min' => 1, 'max' => 5],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
        ]);
    }
}
