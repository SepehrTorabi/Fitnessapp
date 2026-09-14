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
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.consumedAt', ':now')
            ->where('t.user = :user')
            ->andWhere('t.consumedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
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
