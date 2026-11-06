<?php

namespace Wexample\SymfonyWorkspace\Repository;

use Doctrine\ORM\QueryBuilder;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyWorkspace\Entity\WorkspaceLayout;

/**
 * @method WorkspaceLayout|null find($id, $lockMode = null, $lockVersion = null)
 * @method WorkspaceLayout|null findOneBy(array $criteria, array $orderBy = null)
 */
class WorkspaceLayoutRepository extends AbstractRepository
{
    public static function getEntityClassName(): string
    {
        return WorkspaceLayout::class;
    }

    /**
     * The arrangements of a page, about a record or not, in the order they
     * were made.
     *
     * @return WorkspaceLayout[]
     */
    public function findByKey(
        string $layoutKey,
        ?string $subjectType,
        ?string $subjectId,
    ): array {
        return $this->queryByKey($layoutKey, $subjectType, $subjectId)
            ->orderBy('layout.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** The one of them chosen last, or else the one changed last. */
    public function findCurrent(
        string $layoutKey,
        ?string $subjectType,
        ?string $subjectId,
    ): ?WorkspaceLayout {
        return $this->queryByKey($layoutKey, $subjectType, $subjectId)
            ->orderBy('layout.selected', 'DESC')
            ->addOrderBy('layout.dateUpdated', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function queryByKey(
        string $layoutKey,
        ?string $subjectType,
        ?string $subjectId,
    ): QueryBuilder {
        $builder = $this->createQueryBuilder('layout')
            ->andWhere('layout.layoutKey = :key')
            ->setParameter('key', $layoutKey);

        foreach (['subjectType' => $subjectType, 'subjectId' => $subjectId] as $field => $value) {
            if ($value === null) {
                $builder->andWhere("layout.$field IS NULL");
            } else {
                $builder->andWhere("layout.$field = :$field")->setParameter($field, $value);
            }
        }

        return $builder;
    }
}
