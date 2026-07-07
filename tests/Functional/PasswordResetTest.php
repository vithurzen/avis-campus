<?php

namespace App\Tests\Functional;

use App\Entity\EmailLog;
use App\Entity\StudentProfile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordResetTest extends WebTestCase
{
    use MailerAssertionsTrait;

    private EntityManagerInterface $entityManager;

    /** @var string[] emails created by the tests, cleaned up in tearDown */
    private array $createdEmails = [];

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            foreach ($this->createdEmails as $email) {
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($user !== null) {
                    foreach ($this->entityManager->getRepository(EmailLog::class)->findBy(['user' => $user]) as $log) {
                        $this->entityManager->remove($log);
                    }
                    $this->entityManager->remove($user); // profile is cascade-removed
                }
            }
            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testRequestForExistingEmailSendsResetMail(): void
    {
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $email = 'reset-exists@verify-test.example';
        $this->createUser($email, 'oldpassword');

        $this->submitForgot($client, $email);

        self::assertResponseRedirects('/login');
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        \assert($message instanceof Email);
        self::assertSame('Réinitialisation de votre mot de passe', $message->getSubject());

        $log = $this->entityManager->getRepository(EmailLog::class)->findOneBy([
            'recipient' => $email,
            'type' => 'password_reset',
        ]);
        self::assertNotNull($log);
        self::assertSame('sent', $log->getStatus());
    }

    public function testRequestForUnknownEmailSendsNothing(): void
    {
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->submitForgot($client, 'nobody@verify-test.example');

        // Same neutral outcome, but no email is sent (no account enumeration).
        self::assertResponseRedirects('/login');
        self::assertEmailCount(0);
    }

    public function testValidLinkResetsPassword(): void
    {
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $email = 'reset-valid@verify-test.example';
        $this->createUser($email, 'oldpassword');

        $this->submitForgot($client, $email);
        $resetUrl = $this->extractResetUrl();

        $client->request('GET', $resetUrl);
        self::assertResponseIsSuccessful();

        $client->submitForm('Réinitialiser le mot de passe', [
            'change_password[plainPassword][first]' => 'brandnewpass',
            'change_password[plainPassword][second]' => 'brandnewpass',
        ]);
        self::assertResponseRedirects('/login');

        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'brandnewpass'), 'Password should be the new one.');
        self::assertTrue($user->isVerified());
    }

    public function testUsedLinkIsRejected(): void
    {
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $email = 'reset-used@verify-test.example';
        $this->createUser($email, 'oldpassword');

        $this->submitForgot($client, $email);
        $resetUrl = $this->extractResetUrl();

        // Consume the link.
        $client->request('GET', $resetUrl);
        $client->submitForm('Réinitialiser le mot de passe', [
            'change_password[plainPassword][first]' => 'brandnewpass',
            'change_password[plainPassword][second]' => 'brandnewpass',
        ]);

        // Re-using the same link now fails: the selector no longer matches the new hash.
        $client->request('GET', $resetUrl);
        self::assertResponseRedirects('/forgot-password');
    }

    public function testTamperedSignatureIsRejected(): void
    {
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $email = 'reset-tamper@verify-test.example';
        $this->createUser($email, 'oldpassword');

        $this->submitForgot($client, $email);
        $resetUrl = $this->extractResetUrl();

        $client->request('GET', $resetUrl.'tampered');
        self::assertResponseRedirects('/forgot-password');

        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'oldpassword'), 'Password must be unchanged.');
    }

    private function submitForgot(KernelBrowser $client, string $email): void
    {
        $crawler = $client->request('GET', '/forgot-password');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Envoyer le lien')->form([
            'forgot_password_request[email]' => $email,
        ]);
        $client->submit($form);
    }

    private function extractResetUrl(): string
    {
        $message = self::getMailerMessage();
        \assert($message instanceof Email);
        $html = $message->getHtmlBody();
        self::assertMatchesRegularExpression('#/reset-password/#', (string) $html);
        preg_match('#href="([^"]*/reset-password/[^"]+)"#', (string) $html, $m);

        return html_entity_decode($m[1]);
    }

    private function createUser(string $email, string $plainPassword): void
    {
        $this->createdEmails[] = $email;

        $now = new \DateTimeImmutable();
        $user = new User();
        $user->setEmail($email)
            ->setRoles(['ROLE_STUDENT'])
            ->setIsVerified(true)
            ->setCreatedAt($now);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));

        $profile = new StudentProfile();
        $profile->setFirstName('Alice')->setLastName('Martin')->setCreatedAt($now);
        $user->setProfile($profile);

        $this->entityManager->persist($user);
        $this->entityManager->persist($profile);
        $this->entityManager->flush();
    }
}
