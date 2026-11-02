<?php

namespace Wexample\SymfonyWorkspace\Service;

use Doctrine\ORM\EntityManagerInterface;
use Wexample\SymfonyHelpers\Entity\Interfaces\UserEntityInterface;
use Wexample\SymfonyWorkspace\Entity\WorkspaceLayout;
use Wexample\SymfonyWorkspace\Repository\WorkspaceLayoutRepository;

/**
 * Reads and keeps the arrangements of workspaces. What an account left wins
 * over what is offered to everyone; nothing kept, the page's own is drawn.
 */
class WorkspaceLayoutService
{
    public function __construct(
        private readonly WorkspaceLayoutRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findTree(
        string $layoutKey,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?UserEntityInterface $owner = null,
    ): ?array {
        $layout = ($owner ? $this->repository->findOneByKey($layoutKey, $subjectType, $subjectId, $owner) : null)
            ?? $this->repository->findOneByKey($layoutKey, $subjectType, $subjectId, null);

        return $layout?->getTree();
    }

    /**
     * Kept for the account, or offered to everyone without one: which of the
     * two a caller may write is the caller's to decide.
     */
    public function saveTree(
        string $layoutKey,
        array $tree,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?UserEntityInterface $owner = null,
    ): WorkspaceLayout {
        $layout = $this->repository->findOneByKey($layoutKey, $subjectType, $subjectId, $owner);

        if (! $layout) {
            $layout = (new WorkspaceLayout($layoutKey))
                ->setSubject($subjectType, $subjectId)
                ->setOwner($owner);

            $this->entityManager->persist($layout);
        }

        $layout->setTree($tree);
        $this->entityManager->flush();

        return $layout;
    }
}
