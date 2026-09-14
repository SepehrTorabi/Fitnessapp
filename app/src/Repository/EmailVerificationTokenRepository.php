<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailVerificationToken>
 */
class EmailVerificationTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailVerificationToken::class);
    }

    public function findOneByHash(string $tokenHash): ?EmailVerificationToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * Invalidate every outstanding token of a user. Called before issuing a new
     * one, so that a resent confirmation mail silently retires the earlier link
     * instead of leaving several valid ones in the user's inbox.
     */
    public function consumeAllFor(User $user): void
    {
        $outstanding = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.consumedAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        // Loading the tokens and mutating them, rather than issuing a bulk DQL
        // UPDATE. A bulk update writes straight to the database and leaves the
        // UnitOfWork untouched, so a token object already loaded in this request
        // would go on reporting itself as unconsumed - and could still be
        // redeemed. The caller flushes, so the retirements and the replacement
        // token are written together.
        foreach ($outstanding as $token) {
            $token->consume();
        }
    }

    /**
     * Housekeeping: drop tokens that are both expired and already used up.
     */
    public function deleteExpired(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->where('t.expiresAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
