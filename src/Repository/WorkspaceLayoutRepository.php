<?php

namespace Wexample\SymfonyWorkspace\Repository;

use Doctrine\ORM\QueryBuilder;
use Wexample\SymfonyHelpers\Entity\Interfaces\UserEntityInterface;
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
     * The one arrangement of a page, about a record or not, for an account or
     * offered to everyone (`owner` null).
     */
    public function findOneByKey(
        string $layoutKey,
        ?string $subjectType,
        ?string $subjectId,
        ?UserEntityInterface $owner,
    ): ?WorkspaceLayout {
        return $this->queryByKey($layoutKey, $subjectType, $subjectId, $owner)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The arrangements an account keeps of a page, in the order it made them.
     *
     * @return WorkspaceLayout[]
     */
    public function findByKey(
        string $layoutKey,
        ?string $subjectType,
        ?string $subjectId,
        UserEntityInterface $owner,
    ): array {
        return $this->queryByKey($layoutKey, $subjectType, $subjectId, $owner)
            ->orderBy('layout.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** The one of them the account chose last, or else the one changed last. */
    public function findCurrent(
        string $layoutKey,
        ?string $subjectType,
        ?string $subjectId,
        UserEntityInterface $owner,
    ): ?WorkspaceLayout {
        return $this->queryByKey($layoutKey, $subjectType, $subjectId, $owner)
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
        ?UserEntityInterface $owner,
    ): QueryBuilder {
        $builder = $this->createQueryBuilder('layout')
            ->andWhere('layout.layoutKey = :key')
            ->setParameter('key', $layoutKey);

        foreach (['subjectType' => $subjectType, 'subjectId' => $subjectId, 'owner' => $owner] as $field => $value) {
            if ($value === null) {
                $builder->andWhere("layout.$field IS NULL");
            } else {
                $builder->andWhere("layout.$field = :$field")->setParameter($field, $value);
            }
        }

        return $builder;
    }
}
