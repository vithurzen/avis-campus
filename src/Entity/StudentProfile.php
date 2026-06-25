<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class StudentProfile extends Profile
{
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $level = null;

    #[ORM\Column(nullable: true)]
    private ?int $currentYear = null;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(name: 'formation_id', referencedColumnName: 'id', nullable: true)]
    private ?Formation $formation = null;

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getCurrentYear(): ?int
    {
        return $this->currentYear;
    }

    public function setCurrentYear(?int $currentYear): static
    {
        $this->currentYear = $currentYear;

        return $this;
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
}
