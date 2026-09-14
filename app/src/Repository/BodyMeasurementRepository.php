<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BodyMeasurement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BodyMeasurement>
 */
class BodyMeasurementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BodyMeasurement::class);
    }

    public function findOneForUserOn(User $user, \DateTimeImmutable $date): ?BodyMeasurement
    {
        return $this->findOneBy(['user' => $user, 'measuredOn' => $date]);
    }

    /**
     * The most recent weigh-in, optionally as of a given day. Passing a date
     * makes a past day's calorie target reproducible: it uses the body data
     * that was current back then, not today's.
     */
    public function findLatestForUser(User $user, ?\DateTimeImmutable $asOf = null): ?BodyMeasurement
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.measuredOn', 'DESC')
            ->setMaxResults(1);

        if (null !== $asOf) {
            $qb->andWhere('m.measuredOn <= :asOf')->setParameter('asOf', $asOf);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @return list<BodyMeasurement>
     */
    public function findForUserBetween(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.user = :user')
            ->andWhere('m.measuredOn BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.measuredOn', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
