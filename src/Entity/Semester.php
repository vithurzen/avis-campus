<?php

namespace App\Entity;

use App\Repository\SemesterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SemesterRepository::class)]
#[ORM\Table(name: 'semesters')]
class Semester
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['semester:read', 'course:list', 'course:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Formation::class, inversedBy: 'semesters')]
    #[ORM\JoinColumn(name: 'formation_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['semester:read', 'course:read'])]
    private ?Formation $formation = null;

    #[ORM\Column(length: 50)]
    #[Groups(['semester:read', 'course:list', 'course:read'])]
    private ?string $name = null;

    #[ORM\Column]
    #[Groups(['semester:read', 'course:list', 'course:read'])]
    private ?int $number = null;

    /**
     * @var Collection<int, Course>
     */
    #[ORM\OneToMany(mappedBy: 'semester', targetEntity: Course::class)]
    private Collection $courses;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(int $number): static
    {
        $this->number = $number;

        return $this;
    }

    /**
     * @return Collection<int, Course>
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(Course $course): static
    {
        if (!$this->courses->contains($course)) {
            $this->courses->add($course);
            $course->setSemester($this);
        }

        return $this;
    }

    public function removeCourse(Course $course): static
    {
        if ($this->courses->removeElement($course)) {
            if ($course->getSemester() === $this) {
                $course->setSemester(null);
            }
        }

        return $this;
    }
}
