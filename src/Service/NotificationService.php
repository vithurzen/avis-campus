<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates and manages in-app notifications.
 */
class NotificationService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function notify(User $user, string $title, string $message): Notification
    {
        $notification = (new Notification())
            ->setUser($user)
            ->setTitle($title)
            ->setMessage($message);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    public function markAsRead(Notification $notification): void
    {
        $notification->setIsRead(true);
        $this->entityManager->flush();
    }
}
