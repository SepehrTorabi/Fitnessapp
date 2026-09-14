<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityEntry>
 */
class ActivityEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityEntry::class);
    }

    public function save(ActivityEntry $entry, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entry);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ActivityEntry $entry, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entry);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<ActivityEntry>
     */
    public function findForUserOn(User $user, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.performedOn = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Every activity this user has logged, newest day first.
     *
     * @return list<ActivityEntry>
     */
    public function findAllForUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.performedOn', 'DESC')
            ->addOrderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUser(int $id, User $user): ?ActivityEntry
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * Calories burned per day over a range, for the dashboard chart.
     *
     * @return array<string, float> keyed by date in Y-m-d
     */
    public function sumPerDay(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.performedOn AS day')
            ->addSelect('SUM(a.caloriesBurned) AS burned')
            ->where('a.user = :user')
            ->andWhere('a.performedOn BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('a.performedOn')
            ->getQuery()
            ->getArrayResult();

        $totals = [];

        foreach ($rows as $row) {
            $day = $row['day'] instanceof \DateTimeInterface
                ? $row['day']->format('Y-m-d')
                : (string) $row['day'];

            $totals[$day] = (float) $row['burned'];
        }

        return $totals;
    }
}
