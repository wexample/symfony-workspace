<?php

namespace Wexample\SymfonyWorkspace\Repository;

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
        $builder = $this->createQueryBuilder('layout')
            ->andWhere('layout.layoutKey = :key')
            ->setParameter('key', $layoutKey)
            ->setMaxResults(1);

        foreach (['subjectType' => $subjectType, 'subjectId' => $subjectId, 'owner' => $owner] as $field => $value) {
            if ($value === null) {
                $builder->andWhere("layout.$field IS NULL");
            } else {
                $builder->andWhere("layout.$field = :$field")->setParameter($field, $value);
            }
        }

        return $builder->getQuery()->getOneOrNullResult();
    }
}
