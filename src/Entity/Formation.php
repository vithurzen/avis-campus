<?php

namespace App\Entity;

use App\Repository\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: FormationRepository::class)]
#[ORM\Table(name: 'formations')]
class Formation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['formation:read', 'semester:read', 'course:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Groups(['formation:read', 'semester:read', 'course:read'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['formation:read'])]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['formation:read', 'semester:read', 'course:read'])]
    private ?string $degreeLevel = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['formation:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Semester>
     */
    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: Semester::class)]
    private Collection $semesters;

    public function __construct()
    {
        $this->semesters = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDegreeLevel(): ?string
    {
        return $this->degreeLevel;
    }

    public function setDegreeLevel(?string $degreeLevel): static
    {
        $this->degreeLevel = $degreeLevel;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, Semester>
     */
    public function getSemesters(): Collection
    {
        return $this->semesters;
    }

    public function addSemester(Semester $semester): static
    {
        if (!$this->semesters->contains($semester)) {
            $this->semesters->add($semester);
            $semester->setFormation($this);
        }

        return $this;
    }

    public function removeSemester(Semester $semester): static
    {
        if ($this->semesters->removeElement($semester)) {
            if ($semester->getFormation() === $this) {
                $semester->setFormation(null);
            }
        }

        return $this;
    }
}
