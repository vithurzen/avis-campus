<?php

namespace App\Entity;

use App\Repository\ReviewRatingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ReviewRatingRepository::class)]
#[ORM\Table(name: 'review_ratings')]
#[ORM\UniqueConstraint(name: 'uniq_rating_review_criteria', columns: ['review_id', 'rating_criteria_id'])]
class ReviewRating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Review::class, inversedBy: 'ratings')]
    #[ORM\JoinColumn(name: 'review_id', referencedColumnName: 'id', nullable: false)]
    private ?Review $review = null;

    #[ORM\ManyToOne(targetEntity: RatingCriteria::class)]
    #[ORM\JoinColumn(name: 'rating_criteria_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['review:read'])]
    private ?RatingCriteria $ratingCriteria = null;

    #[ORM\Column]
    #[Groups(['review:read'])]
    private ?int $score = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(?Review $review): static
    {
        $this->review = $review;

        return $this;
    }

    public function getRatingCriteria(): ?RatingCriteria
    {
        return $this->ratingCriteria;
    }

    public function setRatingCriteria(?RatingCriteria $ratingCriteria): static
    {
        $this->ratingCriteria = $ratingCriteria;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }
}
