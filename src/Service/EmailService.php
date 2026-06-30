<?php

namespace App\Service;

use App\Entity\EmailLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
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

    /**
     * Sends an HTML email rendered from a Twig template and records the attempt.
     *
     * @param array<string, mixed> $context
     */
    public function sendTemplate(string $recipient, string $subject, string $template, array $context, string $type, ?User $user = null): EmailLog
    {
        $log = (new EmailLog())
            ->setUser($user)
            ->setRecipient($recipient)
            ->setSubject($subject)
            ->setType($type)
            ->setSentAt(new \DateTimeImmutable());

        try {
            $email = (new TemplatedEmail())
                ->to($recipient)
                ->subject($subject)
                ->htmlTemplate($template)
                ->context($context);
            $this->mailer->send($email);
            $log->setStatus('sent');
        } catch (TransportExceptionInterface) {
            $log->setStatus('failed');
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    /**
     * Sends the same templated email to several recipients (one log per recipient).
     *
     * @param User[]               $recipients
     * @param array<string, mixed> $context
     */
    public function sendTemplateToMany(array $recipients, string $subject, string $template, array $context, string $type): void
    {
        foreach ($recipients as $recipient) {
            $this->sendTemplate($recipient->getEmail(), $subject, $template, $context, $type, $recipient);
        }
    }
}
