<?php

namespace App\Tests\Functional;

use App\Entity\EmailLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationConfirmationTest extends WebTestCase
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
                    // email_logs reference the user without cascade, remove them first
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

    public function testRegistrationCreatesUnverifiedUserAndSendsConfirmationEmail(): void
    {
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $email = 'flow@verify-test.example';
        $this->createdEmails[] = $email;

        $this->submitRegistration($client, $email);

        // The user is redirected to login, NOT logged in and sent to the home page.
        self::assertResponseRedirects('/login');

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        self::assertFalse($user->isVerified(), 'A freshly registered account must stay unverified.');

        // Exactly one confirmation email was sent.
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertSame('Confirmez votre inscription sur Avis Campus', $message->getSubject());

        // It was recorded in the email log with the expected type.
        $log = $this->entityManager->getRepository(EmailLog::class)->findOneBy(['user' => $user]);
        self::assertNotNull($log);
        self::assertSame('registration_confirmation', $log->getType());
        self::assertSame('sent', $log->getStatus());
    }

    public function testValidConfirmationLinkActivatesAccountAndLogsIn(): void
    {
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $email = 'activate@verify-test.example';
        $this->createdEmails[] = $email;

        $this->submitRegistration($client, $email);
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        self::assertFalse($user->isVerified());

        // Re-sign a valid link for this user with the same signer the controller uses.
        $client->request('GET', $this->signedVerifyUrl($user->getId()));

        // Confirmation logs the user in and redirects away from /login.
        self::assertResponseStatusCodeSame(302);
        self::assertNotSame('/login', $client->getResponse()->headers->get('Location'));

        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertTrue($user->isVerified(), 'Clicking a valid link must verify the account.');
    }

    public function testTamperedConfirmationLinkIsRejected(): void
    {
        $client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $email = 'tamper@verify-test.example';
        $this->createdEmails[] = $email;

        $this->submitRegistration($client, $email);
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        // Corrupt the signature.
        $client->request('GET', $this->signedVerifyUrl($user->getId()).'tampered');
        self::assertResponseRedirects('/login');

        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertFalse($user->isVerified(), 'A tampered link must not verify the account.');
    }

    private function submitRegistration(KernelBrowser $client, string $email): void
    {
        $crawler = $client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer mon compte')->form([
            'registration_form[firstName]' => 'Alice',
            'registration_form[lastName]' => 'Martin',
            'registration_form[email]' => $email,
            'registration_form[plainPassword]' => 'password123',
            'registration_form[agreeTerms]' => true,
        ]);
        $client->submit($form);
    }

    private function signedVerifyUrl(int $userId): string
    {
        $signer = static::getContainer()->get('uri_signer');
        \assert($signer instanceof UriSigner);
        $urlGenerator = static::getContainer()->get(UrlGeneratorInterface::class);

        return $signer->sign(
            $urlGenerator->generate('app_verify_email', ['id' => $userId], UrlGeneratorInterface::ABSOLUTE_URL),
            new \DateTimeImmutable('+1 hour'),
        );
    }
}
