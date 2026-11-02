<?php

namespace Wexample\SymfonyWorkspace\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyHelpers\Controller\AbstractController;
use Wexample\SymfonyHelpers\Entity\Interfaces\UserEntityInterface;
use Wexample\SymfonyWorkspace\Service\WorkspaceLayoutService;

/**
 * Where a workspace keeps how it was cut, for the account arranging it.
 * Without an account nothing is written here: the board keeps it in the
 * visitor's own state instead.
 */
#[Route(path: '_workspace/layout/', name: 'workspace_layout_')]
final class WorkspaceLayoutController extends AbstractController
{
    final public const string ROUTE_SAVE = 'save';

    final public const string CSRF_TOKEN_ID = 'workspace_layout';

    // An arrangement is a tree of a few dozen zones; past this, it is not one.
    private const int TREE_LENGTH_MAX = 65536;

    #[Route(path: self::ROUTE_SAVE, name: self::ROUTE_SAVE, methods: ['POST'])]
    public function save(
        Request $request,
        WorkspaceLayoutService $layoutService,
    ): Response {
        $user = $this->getUser();

        if (! $user instanceof UserEntityInterface) {
            return new JsonResponse(['error' => 'No account to keep it for.'], Response::HTTP_FORBIDDEN);
        }

        if (! $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'Invalid token.'], Response::HTTP_FORBIDDEN);
        }

        $content = $request->getContent();
        $payload = strlen($content) <= self::TREE_LENGTH_MAX ? json_decode($content, true) : null;

        if (! is_array($payload) || ! is_string($payload['layoutKey'] ?? null) || ! is_array($payload['tree'] ?? null)) {
            return new JsonResponse(['error' => 'Invalid arrangement.'], Response::HTTP_BAD_REQUEST);
        }

        $layoutService->saveTree(
            layoutKey: $payload['layoutKey'],
            tree: $payload['tree'],
            subjectType: is_string($payload['subjectType'] ?? null) ? $payload['subjectType'] : null,
            subjectId: is_scalar($payload['subjectId'] ?? null) ? (string) $payload['subjectId'] : null,
            owner: $user,
        );

        return new JsonResponse(['saved' => true]);
    }
}
