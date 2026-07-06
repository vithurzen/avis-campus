<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\Formation;
use App\Entity\Review;
use App\Entity\Semester;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Repository\FormationRepository;
use App\Repository\SemesterRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire d'avis avec sélecteur en cascade Formation → Semestre → Cours.
 *
 * `Review` ne possède que la relation `course` ; `formation` et `semester` sont
 * des champs non mappés (`mapped => false`) servant uniquement à piloter la
 * cascade. Les choix des champs dépendants sont (re)construits dynamiquement via
 * les Form Events PRE_SET_DATA (affichage / édition) et PRE_SUBMIT (soumission).
 */
class ReviewType extends AbstractType
{
    public function __construct(
        private readonly Security $security,
        private readonly CourseRepository $courseRepository,
        private readonly SemesterRepository $semesterRepository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('formation', EntityType::class, [
                'class'         => Formation::class,
                'choice_label'  => 'name',
                'mapped'        => false,
                'required'      => true,
                'placeholder'   => 'Choisir une formation',
                'query_builder' => static fn (FormationRepository $r) => $r->createQueryBuilder('f')->orderBy('f.name', 'ASC'),
            ])
            ->add('title')
            ->add('content')
            ->add('rating', IntegerType::class, [
                'required' => false,
                'attr'     => ['min' => 1, 'max' => 5],
            ])
        ;

        // Champs dépendants construits dynamiquement (voir listeners).
        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'onPreSetData']);
        $builder->addEventListener(FormEvents::POST_SET_DATA, [$this, 'onPostSetData']);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onPreSubmit']);
    }

    /**
     * Affichage initial / édition : on déduit la formation et le semestre à
     * partir du cours de l'avis, puis on construit les choix de la cascade.
     */
    public function onPreSetData(FormEvent $event): void
    {
        $form = $event->getForm();
        [$formation, $semester, $course] = $this->cascadeFrom($event->getData());

        $this->addSemesterField($form, $this->semesterChoicesFor($formation));
        $this->addCourseField($form, $this->courseChoicesFor($semester, $course));
    }

    /**
     * Pré-sélection des champs non mappés (formation/semestre) : elle doit se
     * faire après le mapping automatique du formulaire (POST_SET_DATA), sinon
     * le DataMapper de Symfony réinitialise tout champ non mappé à sa donnée
     * par défaut (null) juste après PRE_SET_DATA.
     */
    public function onPostSetData(FormEvent $event): void
    {
        $form = $event->getForm();
        [$formation, $semester] = $this->cascadeFrom($event->getData());

        if ($formation !== null) {
            $form->get('formation')->setData($formation);
        }
        if ($semester !== null) {
            $form->get('semester')->setData($semester);
        }
    }

    /**
     * @return array{0: ?Formation, 1: ?Semester, 2: ?Course}
     */
    private function cascadeFrom(mixed $review): array
    {
        $course    = $review instanceof Review ? $review->getCourse() : null;
        $semester  = $course?->getSemester();
        $formation = $semester?->getFormation();

        return [$formation, $semester, $course];
    }

    /**
     * Soumission : on reconstruit les choix des champs dépendants à partir des
     * identifiants soumis pour que les valeurs postées soient valides.
     */
    public function onPreSubmit(FormEvent $event): void
    {
        $data = $event->getData();
        $form = $event->getForm();

        $formationId = $data['formation'] ?? null;
        $semesterId  = $data['semester'] ?? null;

        $semesterChoices = $formationId
            ? $this->semesterRepository->findBy(['formation' => $formationId], ['number' => 'ASC'])
            : [];
        $this->addSemesterField($form, $semesterChoices);

        $semester = $semesterId ? $this->semesterRepository->find($semesterId) : null;
        $this->addCourseField($form, $this->courseChoicesFor($semester, null));
    }

    /**
     * @return Semester[]
     */
    private function semesterChoicesFor(?Formation $formation): array
    {
        // Déjà triés par `number` grâce au #[ORM\OrderBy] sur Formation::$semesters.
        return $formation !== null ? $formation->getSemesters()->toArray() : [];
    }

    /**
     * Cours du semestre non encore reviewés par l'utilisateur courant.
     * En édition, $current est réintégré pour rester sélectionnable.
     *
     * @return Course[]
     */
    private function courseChoicesFor(?Semester $semester, ?Course $current): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || $semester === null) {
            return $current !== null ? [$current] : [];
        }

        return $this->courseRepository
            ->findCoursesNotReviewedBy($user, $current, $semester)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Semester[] $choices
     */
    private function addSemesterField(FormInterface $form, array $choices): void
    {
        $form->add('semester', EntityType::class, [
            'class'        => Semester::class,
            'choices'      => $choices,
            'choice_label' => 'name',
            'mapped'       => false,
            'required'     => true,
            'placeholder'  => 'Choisir un semestre',
        ]);
    }

    /**
     * @param Course[] $choices
     */
    private function addCourseField(FormInterface $form, array $choices): void
    {
        $form->add('course', EntityType::class, [
            'class'        => Course::class,
            'choices'      => $choices,
            'choice_label' => 'title',
            'required'     => true,
            'placeholder'  => 'Choisir un cours',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
        ]);
    }
}
