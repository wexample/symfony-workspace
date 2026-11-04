<?php

namespace Wexample\SymfonyWorkspace\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
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

    /** The arrangement a page opens on: the account's current one, or the one offered to everyone. */
    public function findTree(
        string $layoutKey,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?UserEntityInterface $owner = null,
    ): ?array {
        return $this->findCurrent($layoutKey, $subjectType, $subjectId, $owner)?->getTree();
    }

    public function findCurrent(
        string $layoutKey,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?UserEntityInterface $owner = null,
    ): ?WorkspaceLayout {
        return ($owner ? $this->repository->findCurrent($layoutKey, $subjectType, $subjectId, $owner) : null)
            ?? $this->repository->findOneByKey($layoutKey, $subjectType, $subjectId, null);
    }

    /**
     * The arrangements an account keeps of a page, as its board lists them.
     *
     * @return array<array{id: string, name: ?string}>
     */
    public function describeOwned(
        string $layoutKey,
        ?string $subjectType,
        ?string $subjectId,
        UserEntityInterface $owner,
    ): array {
        return array_map(
            fn (WorkspaceLayout $layout): array => self::describe($layout),
            $this->repository->findByKey($layoutKey, $subjectType, $subjectId, $owner),
        );
    }

    /** @return array{id: string, name: ?string} */
    public static function describe(WorkspaceLayout $layout): array
    {
        return ['id' => (string) $layout->getId(), 'name' => $layout->getName()];
    }

    /**
     * Kept for the account, or offered to everyone without one: which of the
     * two a caller may write is the caller's to decide. Into the arrangement
     * named (`id`) when it is the account's, into its current one else — made
     * the first time.
     */
    public function saveTree(
        string $layoutKey,
        array $tree,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?UserEntityInterface $owner = null,
        ?string $id = null,
    ): WorkspaceLayout {
        $layout = ($id && $owner ? $this->findOwned($id, $owner) : null)
            ?? ($owner
                ? $this->repository->findCurrent($layoutKey, $subjectType, $subjectId, $owner)
                : $this->repository->findOneByKey($layoutKey, $subjectType, $subjectId, null));

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

    /** One more arrangement of a page for the account, named, chosen at once. */
    public function create(
        string $layoutKey,
        array $tree,
        ?string $name,
        ?string $subjectType,
        ?string $subjectId,
        UserEntityInterface $owner,
    ): WorkspaceLayout {
        $layout = (new WorkspaceLayout($layoutKey))
            ->setSubject($subjectType, $subjectId)
            ->setOwner($owner)
            ->setName($name)
            ->setTree($tree);
        $this->entityManager->persist($layout);
        $this->markSelected($layout);
        $this->entityManager->flush();

        return $layout;
    }

    /** One of the account's arrangements chosen: the page opens on it from now on. */
    public function select(string $id, UserEntityInterface $owner): ?WorkspaceLayout
    {
        $layout = $this->findOwned($id, $owner);

        if ($layout) {
            $this->markSelected($layout);
            $this->entityManager->flush();
        }

        return $layout;
    }

    public function rename(string $id, ?string $name, UserEntityInterface $owner): ?WorkspaceLayout
    {
        $layout = $this->findOwned($id, $owner)?->setName($name);
        $this->entityManager->flush();

        return $layout;
    }

    // The one chosen, the others of the page no longer.
    private function markSelected(WorkspaceLayout $layout): void
    {
        $siblings = $this->repository->findByKey(
            $layout->getLayoutKey(),
            $layout->getSubjectType(),
            $layout->getSubjectId(),
            $layout->getOwner(),
        );

        foreach ($siblings as $sibling) {
            $sibling->setSelected($sibling === $layout);
        }

        $layout->setSelected(true);
    }

    /** An arrangement by its id, when it is the account's: none else. */
    private function findOwned(string $id, UserEntityInterface $owner): ?WorkspaceLayout
    {
        $layout = Uuid::isValid($id) ? $this->repository->find($id) : null;

        return $layout && $layout->getOwner() === $owner ? $layout : null;
    }
}
