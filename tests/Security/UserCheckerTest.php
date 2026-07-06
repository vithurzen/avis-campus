<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\InMemoryUser;

class UserCheckerTest extends TestCase
{
    public function testUnverifiedUserIsRejected(): void
    {
        $user = (new User())->setIsVerified(false);

        $this->expectException(CustomUserMessageAccountStatusException::class);

        (new UserChecker())->checkPreAuth($user);
    }

    public function testVerifiedUserIsAllowed(): void
    {
        $user = (new User())->setIsVerified(true);

        (new UserChecker())->checkPreAuth($user);

        $this->addToAssertionCount(1); // no exception thrown
    }

    public function testNonAppUserIsIgnored(): void
    {
        // Foreign UserInterface implementations must not trigger the verification gate.
        (new UserChecker())->checkPreAuth(new InMemoryUser('someone', 'pass'));

        $this->addToAssertionCount(1);
    }
}
