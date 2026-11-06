<?php

namespace Wexample\SymfonyWorkspace\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Entity\Interfaces\UserEntityInterface;
use Wexample\SymfonyWorkspace\Entity\WorkspaceLayout;
use Wexample\SymfonyWorkspace\Service\WorkspaceLayoutService;

/**
 * Where a workspace keeps how it was cut, for everyone who opens the page —
 * and the arrangements it keeps of one page, made, chosen and renamed. Only
 * a signed-in account writes here: a visitor's board keeps it in the
 * visitor's own state instead.
 */
#[Route(path: '_workspace/layout/', name: 'workspace_layout_')]
final class WorkspaceLayoutController extends AbstractController
{
    final public const string ROUTE_SAVE = 'save';

    final public const string ROUTE_CREATE = 'create';

    final public const string ROUTE_SELECT = 'select';

    final public const string ROUTE_RENAME = 'rename';

    final public const string CSRF_TOKEN_ID = 'workspace_layout';

    // An arrangement is a tree of a few dozen zones; past this, it is not one.
    private const int TREE_LENGTH_MAX = 65536;

    private const int NAME_LENGTH_MAX = 255;

    /** Into the arrangement named (`id`), or the page's current one: answered with its id. */
    #[Route(path: self::ROUTE_SAVE, name: self::ROUTE_SAVE, methods: ['POST'])]
    public function save(Request $request, WorkspaceLayoutService $layoutService): Response
    {
        return $this->handle($request, function (array $payload) use ($layoutService): Response {
            if (! is_string($payload['layoutKey'] ?? null) || ! is_array($payload['tree'] ?? null)) {
                return $this->invalid();
            }

            return $this->answer($layoutService->saveTree(
                layoutKey: $payload['layoutKey'],
                tree: $payload['tree'],
                subjectType: $this->subjectType($payload),
                subjectId: $this->subjectId($payload),
                id: is_string($payload['id'] ?? null) ? $payload['id'] : null,
            ));
        });
    }

    /** One more arrangement of the page, named, starting from the tree sent, chosen at once. */
    #[Route(path: self::ROUTE_CREATE, name: self::ROUTE_CREATE, methods: ['POST'])]
    public function create(Request $request, WorkspaceLayoutService $layoutService): Response
    {
        return $this->handle($request, function (array $payload) use ($layoutService): Response {
            if (! is_string($payload['layoutKey'] ?? null) || ! is_array($payload['tree'] ?? null)) {
                return $this->invalid();
            }

            return $this->answer($layoutService->create(
                layoutKey: $payload['layoutKey'],
                tree: $payload['tree'],
                name: $this->name($payload),
                subjectType: $this->subjectType($payload),
                subjectId: $this->subjectId($payload),
            ));
        });
    }

    /** One of the page's arrangements chosen: answered with its tree. */
    #[Route(path: self::ROUTE_SELECT, name: self::ROUTE_SELECT, methods: ['POST'])]
    public function select(Request $request, WorkspaceLayoutService $layoutService): Response
    {
        return $this->handle($request, function (array $payload) use ($layoutService): Response {
            $layout = is_string($payload['id'] ?? null) ? $layoutService->select($payload['id']) : null;

            return $layout ? $this->answer($layout) : $this->notFound();
        });
    }

    #[Route(path: self::ROUTE_RENAME, name: self::ROUTE_RENAME, methods: ['POST'])]
    public function rename(Request $request, WorkspaceLayoutService $layoutService): Response
    {
        return $this->handle($request, function (array $payload) use ($layoutService): Response {
            $layout = is_string($payload['id'] ?? null) ? $layoutService->rename($payload['id'], $this->name($payload)) : null;

            return $layout ? $this->answer($layout) : $this->notFound();
        });
    }

    /**
     * What every route asks first: an account, the token, a payload that is
     * an object of a reasonable size.
     */
    private function handle(Request $request, callable $then): Response
    {
        $user = $this->getUser();

        if (! $user instanceof UserEntityInterface) {
            return new JsonResponse(['error' => 'Only a signed-in account arranges it.'], Response::HTTP_FORBIDDEN);
        }

        if (! $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'Invalid token.'], Response::HTTP_FORBIDDEN);
        }

        $content = $request->getContent();
        $payload = strlen($content) <= self::TREE_LENGTH_MAX ? json_decode($content, true) : null;

        return is_array($payload) ? $then($payload) : $this->invalid();
    }

    private function answer(WorkspaceLayout $layout): JsonResponse
    {
        return new JsonResponse(WorkspaceLayoutService::describe($layout) + ['tree' => $layout->getTree()]);
    }

    private function invalid(): JsonResponse
    {
        return new JsonResponse(['error' => 'Invalid arrangement.'], Response::HTTP_BAD_REQUEST);
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(['error' => 'No such arrangement.'], Response::HTTP_NOT_FOUND);
    }

    private function subjectType(array $payload): ?string
    {
        return is_string($payload['subjectType'] ?? null) ? $payload['subjectType'] : null;
    }

    private function subjectId(array $payload): ?string
    {
        return is_scalar($payload['subjectId'] ?? null) ? (string) $payload['subjectId'] : null;
    }

    // Trimmed, cut to what the column holds; empty, no name.
    private function name(array $payload): ?string
    {
        $name = is_string($payload['name'] ?? null) ? trim($payload['name']) : '';

        return '' === $name ? null : mb_substr($name, 0, self::NAME_LENGTH_MAX);
    }
}
