<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DiaryEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DiaryEntry>
 */
class DiaryEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiaryEntry::class);
    }

    public function save(DiaryEntry $entry, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entry);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DiaryEntry $entry, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entry);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<DiaryEntry>
     */
    public function findForUserOn(User $user, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.user = :user')
            ->andWhere('d.loggedOn = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->orderBy('d.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Everything this user has ever logged, newest day first and within a day in
     * the order it was entered.
     *
     * Used by the PDF export, which walks whole days rather than querying one at
     * a time - a year of diary is a few hundred rows, and fetching it per day
     * would be a few hundred queries.
     *
     * @return list<DiaryEntry>
     */
    public function findAllForUser(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.user = :user')
            ->setParameter('user', $user)
            ->orderBy('d.loggedOn', 'DESC')
            ->addOrderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUser(int $id, User $user): ?DiaryEntry
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * Per-day intake totals over a date range, in a single grouped query.
     *
     * The dashboard chart needs seven days at once; fetching seven days of
     * entries and summing them in PHP would pull hundreds of rows to produce
     * fourteen numbers. Days with nothing logged are simply absent from the
     * result - the caller fills the gaps, because only it knows the full range.
     *
     * @return array<string, array{kcal: float, proteinG: float, carbsG: float, fatG: float}>
     *         keyed by date in Y-m-d
     */
    public function sumPerDay(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.loggedOn AS day')
            ->addSelect('SUM(d.nutrients.kcal) AS kcal')
            ->addSelect('SUM(d.nutrients.proteinG) AS proteinG')
            ->addSelect('SUM(d.nutrients.carbsG) AS carbsG')
            ->addSelect('SUM(d.nutrients.fatG) AS fatG')
            ->where('d.user = :user')
            ->andWhere('d.loggedOn BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('d.loggedOn')
            ->getQuery()
            ->getArrayResult();

        $totals = [];

        foreach ($rows as $row) {
            $day = $row['day'] instanceof \DateTimeInterface
                ? $row['day']->format('Y-m-d')
                : (string) $row['day'];

            $totals[$day] = [
                'kcal' => (float) $row['kcal'],
                'proteinG' => (float) $row['proteinG'],
                'carbsG' => (float) $row['carbsG'],
                'fatG' => (float) $row['fatG'],
            ];
        }

        return $totals;
    }
}
