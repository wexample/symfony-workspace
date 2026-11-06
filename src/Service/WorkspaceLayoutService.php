<?php

namespace Wexample\SymfonyWorkspace\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyWorkspace\Entity\WorkspaceLayout;
use Wexample\SymfonyWorkspace\Repository\WorkspaceLayoutRepository;

/**
 * Reads and keeps the arrangements of workspaces, the same for everyone who
 * opens the page; nothing kept, the page's own is drawn.
 */
class WorkspaceLayoutService
{
    public function __construct(
        private readonly WorkspaceLayoutRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** The arrangement a page opens on: the one chosen last. */
    public function findTree(
        string $layoutKey,
        ?string $subjectType = null,
        ?string $subjectId = null,
    ): ?array {
        return $this->findCurrent($layoutKey, $subjectType, $subjectId)?->getTree();
    }

    public function findCurrent(
        string $layoutKey,
        ?string $subjectType = null,
        ?string $subjectId = null,
    ): ?WorkspaceLayout {
        return $this->repository->findCurrent($layoutKey, $subjectType, $subjectId);
    }

    /**
     * The arrangements a page keeps, as its board lists them.
     *
     * @return array<array{id: string, name: ?string}>
     */
    public function describeAll(
        string $layoutKey,
        ?string $subjectType,
        ?string $subjectId,
    ): array {
        return array_map(
            fn (WorkspaceLayout $layout): array => self::describe($layout),
            $this->repository->findByKey($layoutKey, $subjectType, $subjectId),
        );
    }

    /** @return array{id: string, name: ?string} */
    public static function describe(WorkspaceLayout $layout): array
    {
        return ['id' => (string) $layout->getId(), 'name' => $layout->getName()];
    }

    /**
     * Into the arrangement named (`id`) when it is one of the page's, into
     * its current one else — made the first time.
     */
    public function saveTree(
        string $layoutKey,
        array $tree,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $id = null,
    ): WorkspaceLayout {
        $layout = ($id ? $this->findOfPage($id, $layoutKey, $subjectType, $subjectId) : null)
            ?? $this->repository->findCurrent($layoutKey, $subjectType, $subjectId);

        if (! $layout) {
            $layout = (new WorkspaceLayout($layoutKey))->setSubject($subjectType, $subjectId);
            $this->entityManager->persist($layout);
        }

        $layout->setTree($tree);
        $this->entityManager->flush();

        return $layout;
    }

    /** One more arrangement of a page, named, chosen at once. */
    public function create(
        string $layoutKey,
        array $tree,
        ?string $name,
        ?string $subjectType,
        ?string $subjectId,
    ): WorkspaceLayout {
        $layout = (new WorkspaceLayout($layoutKey))
            ->setSubject($subjectType, $subjectId)
            ->setName($name)
            ->setTree($tree);
        $this->entityManager->persist($layout);
        $this->markSelected($layout);
        $this->entityManager->flush();

        return $layout;
    }

    /** One of the arrangements chosen: the page opens on it from now on. */
    public function select(string $id): ?WorkspaceLayout
    {
        $layout = $this->find($id);

        if ($layout) {
            $this->markSelected($layout);
            $this->entityManager->flush();
        }

        return $layout;
    }

    public function rename(string $id, ?string $name): ?WorkspaceLayout
    {
        $layout = $this->find($id)?->setName($name);
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
        );

        foreach ($siblings as $sibling) {
            $sibling->setSelected($sibling === $layout);
        }

        $layout->setSelected(true);
    }

    private function find(string $id): ?WorkspaceLayout
    {
        return Uuid::isValid($id) ? $this->repository->find($id) : null;
    }

    /** An arrangement by its id, when it is one of this page's: none else. */
    private function findOfPage(string $id, string $layoutKey, ?string $subjectType, ?string $subjectId): ?WorkspaceLayout
    {
        $layout = $this->find($id);

        return $layout
            && $layout->getLayoutKey() === $layoutKey
            && $layout->getSubjectType() === $subjectType
            && $layout->getSubjectId() === $subjectId
            ? $layout
            : null;
    }
}
