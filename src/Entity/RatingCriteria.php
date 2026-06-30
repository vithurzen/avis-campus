<?php

namespace App\Entity;

use App\Repository\RatingCriteriaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RatingCriteriaRepository::class)]
#[ORM\Table(name: 'rating_criteria')]
class RatingCriteria
{
    public const CRITERIA_DIFFICULTY = 'Difficulté';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => 5])]
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
