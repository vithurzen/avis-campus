<?php

namespace App\Entity;

use App\Repository\RatingCriteriaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: RatingCriteriaRepository::class)]
#[ORM\Table(name: 'rating_criteria')]
class RatingCriteria
{
    public const CRITERIA_DIFFICULTY = 'Difficulté';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['criteria:read', 'rating:read', 'review:list', 'review:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['criteria:read', 'rating:read', 'review:list', 'review:read'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['criteria:read'])]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => 5])]
    #[Groups(['criteria:read', 'rating:read', 'review:list', 'review:read'])]
    private int $maxScore = 5;

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

    public function getMaxScore(): int
    {
        return $this->maxScore;
    }

    public function setMaxScore(int $maxScore): static
    {
        $this->maxScore = $maxScore;

        return $this;
    }
}
