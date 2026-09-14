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
     * The measurement to use when working out the calorie target for a day.
     *
     * Normally the most recent weigh-in on or before that day. When there is
     * none - the day predates every weigh-in, which is what any imported
     * history looks like - it falls back to the earliest one on record.
     *
     * The fallback matters. Without it, importing months of diary and then
     * stepping on the scale once leaves every imported day with no target at
     * all: no budget line, no green or red, just grey bars. "This user has no
     * body data" and "this user had not weighed themselves yet on that date"
     * are different situations, and only the first should produce nothing.
     *
     * The fallback is an approximation, and knowingly so: someone who weighed
     * 20 kg more last winter gets last winter's days judged against today's
     * body. It is still far closer than refusing to answer, and the whole
     * target is an estimate from a formula either way. A user who wants past
     * days judged properly can add a weigh-in dated back then, and it takes
     * over automatically.
     */
    public function findApplicableForUser(User $user, \DateTimeImmutable $on): ?BodyMeasurement
    {
        return $this->findLatestForUser($user, $on) ?? $this->findEarliestForUser($user);
    }

    public function findEarliestForUser(User $user): ?BodyMeasurement
    {
        return $this->createQueryBuilder('m')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.measuredOn', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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
