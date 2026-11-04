<?php

namespace Wexample\SymfonyWorkspace\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Entity\Interfaces\UserEntityInterface;
use Wexample\SymfonyWorkspace\Repository\WorkspaceLayoutRepository;

/**
 * How a workspace is cut, and what each zone shows: the tree a zone-tiling
 * draws, kept as it was left.
 *
 * Found by what the page names it (`layoutKey`), and by the record it is
 * about, if any (`subjectType` / `subjectId`): the dashboard of one project is
 * not the dashboard of another. Kept for one account, or, without one, for
 * whoever has not arranged their own — the arrangement a page offers first.
 * An account may keep several of one page, each by its name, and opens on
 * the one it chose last (`selected`).
 */
#[ORM\Entity(repositoryClass: WorkspaceLayoutRepository::class)]
#[ORM\Table(name: 'workspace_layout')]
#[ORM\Index(columns: ['layout_key', 'subject_type', 'subject_id'])]
class WorkspaceLayout extends AbstractEntity
{
    /**
     * The format of the tree, for when it changes: an arrangement saved under
     * an older one is read through what knows it.
     */
    final public const int TREE_VERSION = 1;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $layoutKey;

    /** The entity it is about, as the api names it, and its id. */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $subjectType = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    protected ?string $subjectId = null;

    /** Whose it is; none, the arrangement offered to everyone. */
    #[ORM\ManyToOne(targetEntity: UserEntityInterface::class)]
    #[ORM\JoinColumn(name: 'owner_id', nullable: true, onDelete: 'CASCADE')]
    protected ?UserEntityInterface $owner = null;

    #[ORM\Column(type: Types::JSON)]
    protected array $tree = [];

    #[ORM\Column(type: Types::INTEGER)]
    protected int $treeVersion = self::TREE_VERSION;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $dateUpdated;

    /**
     * What an account calls it, among the arrangements it keeps of one page:
     * none, the first one, kept before it was given a name.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $name = null;

    /**
     * The one of them the account chose last: the page opens on it. One at
     * most, of a page and a record, for an account.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    protected bool $selected = false;

    public function __construct(string $layoutKey)
    {
        parent::__construct();

        $this->layoutKey = $layoutKey;
        $this->dateUpdated = new DateTimeImmutable();
    }

    public function getLayoutKey(): string
    {
        return $this->layoutKey;
    }

    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): ?string
    {
        return $this->subjectId;
    }

    public function setSubject(?string $type, ?string $id): self
    {
        $this->subjectType = $type;
        $this->subjectId = $id;

        return $this;
    }

    public function getOwner(): ?UserEntityInterface
    {
        return $this->owner;
    }

    public function setOwner(?UserEntityInterface $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getTree(): array
    {
        return $this->tree;
    }

    public function setTree(array $tree): self
    {
        $this->tree = $tree;
        $this->treeVersion = self::TREE_VERSION;
        $this->dateUpdated = new DateTimeImmutable();

        return $this;
    }

    public function getTreeVersion(): int
    {
        return $this->treeVersion;
    }

    public function getDateUpdated(): DateTimeImmutable
    {
        return $this->dateUpdated;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }

    public function setSelected(bool $selected): self
    {
        $this->selected = $selected;

        return $this;
    }

}
