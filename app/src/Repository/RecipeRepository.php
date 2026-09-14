<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Recipe;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recipe>
 */
class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipe::class);
    }

    public function save(Recipe $recipe, bool $flush = true): void
    {
        $this->getEntityManager()->persist($recipe);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Recipes the given user may see: their own, plus anything shared publicly.
     *
     * @return list<Recipe>
     */
    public function findAccessible(User $user, ?string $query = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.ingredients', 'i')
            ->addSelect('i')
            ->leftJoin('i.food', 'f')
            ->addSelect('f')
            ->where('r.createdBy = :user OR r.public = true')
            ->setParameter('user', $user)
            ->orderBy('r.name', 'ASC')
            ->setMaxResults($limit);

        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('LOWER(r.name) LIKE :like')
                ->setParameter('like', '%'.mb_strtolower(trim($query)).'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findAccessibleOneById(int $id, User $user): ?Recipe
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.ingredients', 'i')
            ->addSelect('i')
            ->leftJoin('i.food', 'f')
            ->addSelect('f')
            ->where('r.id = :id')
            ->andWhere('r.createdBy = :user OR r.public = true')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
