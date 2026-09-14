<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Food;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Food>
 */
class FoodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Food::class);
    }

    public function save(Food $food, bool $flush = true): void
    {
        $this->getEntityManager()->persist($food);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByBarcode(string $barcode): ?Food
    {
        return $this->findOneBy(['barcode' => $barcode]);
    }

    /**
     * Free-text search over the local catalogue.
     *
     * Foods created by a user are private to them; seeded and imported foods are
     * visible to everybody. Results starting with the search term come first,
     * which keeps "milk" ahead of "coconut milk drink".
     *
     * @return list<Food>
     */
    public function search(string $query, ?User $user = null, int $limit = 25): array
    {
        $term = trim($query);

        if ('' === $term) {
            return [];
        }

        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.portions', 'p')
            ->addSelect('p')
            ->where('LOWER(f.name) LIKE :like OR LOWER(f.brand) LIKE :like')
            ->setParameter('like', '%'.mb_strtolower($term).'%')
            ->setMaxResults($limit);

        if (null === $user) {
            $qb->andWhere('f.createdBy IS NULL');
        } else {
            $qb->andWhere('f.createdBy IS NULL OR f.createdBy = :user')
                ->setParameter('user', $user);
        }

        // Rank exact and prefix matches above substring matches.
        $qb->addSelect(
            'CASE
                WHEN LOWER(f.name) = :exact THEN 0
                WHEN LOWER(f.name) LIKE :prefix THEN 1
                ELSE 2
            END AS HIDDEN relevance'
        )
            ->setParameter('exact', mb_strtolower($term))
            ->setParameter('prefix', mb_strtolower($term).'%')
            ->orderBy('relevance', 'ASC')
            ->addOrderBy('f.name', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
