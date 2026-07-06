<?php

namespace App\Security\Voter;

use App\Entity\Comment;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class CommentVoter extends Voter
{
    public const EDIT   = 'COMMENT_EDIT';
    public const DELETE = 'COMMENT_DELETE';

    public function __construct(private readonly Security $security) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE], true)
            && $subject instanceof Comment;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $isOwner = $subject->getUser()?->getId() === $user->getId();

        return match ($attribute) {
            self::EDIT   => $isOwner,
            self::DELETE => $isOwner || $this->security->isGranted('ROLE_ADMIN'),
            default      => false,
        };
    }
}
