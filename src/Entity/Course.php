<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\Table(name: 'courses')]
#[ORM\HasLifecycleCallbacks]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['course:list', 'course:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Semester::class, inversedBy: 'courses')]
    #[ORM\JoinColumn(name: 'semester_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['course:list', 'course:read'])]
    private ?Semester $semester = null;

    #[ORM\Column(length: 180)]
    #[Groups(['course:list', 'course:read'])]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['course:list', 'course:read'])]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['course:list', 'course:read'])]
    private ?float $coefficient = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['course:list', 'course:read'])]
    private ?int $hours = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['course:list', 'course:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['course:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Teacher>
     */
    #[ORM\ManyToMany(targetEntity: Teacher::class, inversedBy: 'courses')]
    #[ORM\JoinTable(name: 'course_teachers')]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'teacher_id', referencedColumnName: 'id')]
    #[Groups(['course:read'])]
    private Collection $teachers;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'courses')]
    #[ORM\JoinTable(name: 'course_tags')]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id')]
    #[Groups(['course:list', 'course:read'])]
    private Collection $tags;

    /**
     * @var Collection<int, Resource>
     */
    #[ORM\OneToMany(mappedBy: 'course', targetEntity: Resource::class)]
    #[Groups(['course:read'])]
    private Collection $resources;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(mappedBy: 'course', targetEntity: Review::class)]
    private Collection $reviews;

    /**
     * @var Collection<int, Favorite>
     */
    #[ORM\OneToMany(mappedBy: 'course', targetEntity: Favorite::class)]
    private Collection $favorites;

    public function __construct()
    {
        $this->teachers = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->resources = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->favorites = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSemester(): ?Semester
    {
        return $this->semester;
    }

    public function setSemester(?Semester $semester): static
    {
        $this->semester = $semester;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getCoefficient(): ?float
    {
        return $this->coefficient;
    }

    public function setCoefficient(?float $coefficient): static
    {
        $this->coefficient = $coefficient;

        return $this;
    }

    public function getHours(): ?int
    {
        return $this->hours;
    }

    public function setHours(?int $hours): static
    {
        $this->hours = $hours;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Teacher>
     */
    public function getTeachers(): Collection
    {
        return $this->teachers;
    }

    public function addTeacher(Teacher $teacher): static
    {
        if (!$this->teachers->contains($teacher)) {
            $this->teachers->add($teacher);
        }

        return $this;
    }

    public function removeTeacher(Teacher $teacher): static
    {
        $this->teachers->removeElement($teacher);

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /**
     * @return Collection<int, Resource>
     */
    public function getResources(): Collection
    {
        return $this->resources;
    }

    public function addResource(Resource $resource): static
    {
        if (!$this->resources->contains($resource)) {
            $this->resources->add($resource);
            $resource->setCourse($this);
        }

        return $this;
    }

    public function removeResource(Resource $resource): static
    {
        if ($this->resources->removeElement($resource)) {
            if ($resource->getCourse() === $this) {
                $resource->setCourse(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setCourse($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            if ($review->getCourse() === $this) {
                $review->setCourse(null);
            }
        }

        return $this;
    }

    #[Groups(['course:list', 'course:read'])]
    public function getAverageRating(): float
    {
        $totalScore = 0;
        $count = 0;
        foreach ($this->reviews as $review) {
            if ($review->getStatus() === 'published') {
                foreach ($review->getRatings() as $rating) {
                    $totalScore += $rating->getScore();
                    $count++;
                }
            }
        }
        return $count > 0 ? round($totalScore / $count, 2) : 0.0;
    }

    #[Groups(['course:list', 'course:read'])]
    public function getPublishedReviewCount(): int
    {
        return $this->reviews->filter(fn($r) => $r->getStatus() === 'published')->count();
    }

    /**
     * @return Collection<int, Favorite>
     */
    public function getFavorites(): Collection
    {
        return $this->favorites;
    }

    public function addFavorite(Favorite $favorite): static
    {
        if (!$this->favorites->contains($favorite)) {
            $this->favorites->add($favorite);
            $favorite->setCourse($this);
        }

        return $this;
    }

    public function removeFavorite(Favorite $favorite): static
    {
        if ($this->favorites->removeElement($favorite)) {
            if ($favorite->getCourse() === $this) {
                $favorite->setCourse(null);
            }
        }

        return $this;
    }
}
