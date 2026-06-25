<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AdminProfile extends Profile
{
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $adminCode = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $superAdmin = false;

    public function getAdminCode(): ?string
    {
        return $this->adminCode;
    }

    public function setAdminCode(?string $adminCode): static
    {
        $this->adminCode = $adminCode;

        return $this;
    }

    public function isSuperAdmin(): bool
    {
        return $this->superAdmin;
    }

    public function setSuperAdmin(bool $superAdmin): static
    {
        $this->superAdmin = $superAdmin;

        return $this;
    }
}
