<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ModeratorProfile extends Profile
{
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $moderationArea = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $handledReportsCount = 0;

    public function getModerationArea(): ?string
    {
        return $this->moderationArea;
    }

    public function setModerationArea(?string $moderationArea): static
    {
        $this->moderationArea = $moderationArea;

        return $this;
    }

    public function getHandledReportsCount(): int
    {
        return $this->handledReportsCount;
    }

    public function setHandledReportsCount(int $handledReportsCount): static
    {
        $this->handledReportsCount = $handledReportsCount;

        return $this;
    }
}
