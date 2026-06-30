<?php

namespace App\Service;

use App\Entity\EmailLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Sends emails and records every attempt in an EmailLog.
 */
class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function send(string $recipient, string $subject, string $body, string $type, ?User $user = null): EmailLog
    {
        $log = (new EmailLog())
            ->setUser($user)
            ->setRecipient($recipient)
            ->setSubject($subject)
            ->setType($type)
            ->setSentAt(new \DateTimeImmutable());

        try {
            $email = (new Email())
                ->to($recipient)
                ->subject($subject)
                ->text($body);
            $this->mailer->send($email);
            $log->setStatus('sent');
        } catch (TransportExceptionInterface) {
            $log->setStatus('failed');
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }
}
