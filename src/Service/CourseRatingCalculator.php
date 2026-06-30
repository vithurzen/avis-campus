<?php

namespace App\Service;

use App\Entity\Course;
use App\Repository\ReviewRatingRepository;

/**
 * Computes course rating statistics from approved reviews.
 */
class CourseRatingCalculator
{
    public function __construct(private ReviewRatingRepository $reviewRatingRepository)
    {
    }

    /**
     * Overall average rating of a course (0.0 when it has no approved ratings).
     */
    public function calculateAverage(Course $course): float
    {
        return round($this->reviewRatingRepository->getAverageScoreForCourse($course) ?? 0.0, 2);
    }

    /**
     * Average rating per criteria, e.g. ['Pédagogie' => 4.3, 'Difficulté' => 2.8].
     *
     * @return array<string, float>
     */
    public function averagesByCriteria(Course $course): array
    {
        $result = [];
        foreach ($this->reviewRatingRepository->getAveragesByCriteriaForCourse($course) as $row) {
            $result[$row['name']] = round($row['avg'], 2);
        }

        return $result;
    }
}
