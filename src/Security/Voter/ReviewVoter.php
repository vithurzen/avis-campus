<?php

namespace App\Security\Voter;

use App\Entity\Review;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Object-level authorization for Review actions.
 *
 * - A student may edit/delete their own review only while it is still pending.
 * - A moderator may approve, reject or hide reviews.
 * - An admin may do everything.
 */
final class ReviewVoter extends Voter
{
    public const EDIT    = 'REVIEW_EDIT';
    public const DELETE  = 'REVIEW_DELETE';
    public const APPROVE = 'REVIEW_APPROVE';
    public const REJECT  = 'REVIEW_REJECT';
    public const HIDE    = 'REVIEW_HIDE';

    private const ATTRIBUTES = [
        self::EDIT, self::DELETE, self::APPROVE, self::REJECT, self::HIDE,
    ];

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true) && $subject instanceof Review;
    }

    /**
     * @param Review $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false; // not authenticated
        }

        // Admin can do everything.
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return match ($attribute) {
            self::EDIT, self::DELETE                 => $this->isOwner($subject, $user) && $subject->isPending(),
            self::APPROVE, self::REJECT, self::HIDE  => $this->security->isGranted('ROLE_MODERATOR'),
            default                                  => false,
        };
    }

    private function isOwner(Review $review, User $user): bool
    {
        return $review->getUser()?->getId() === $user->getId();
    }
}
