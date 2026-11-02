<?php

namespace Wexample\SymfonyWorkspace\Twig;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Wexample\SymfonyHelpers\Entity\Interfaces\UserEntityInterface;
use Wexample\SymfonyWorkspace\Controller\WorkspaceLayoutController;
use Wexample\SymfonyWorkspace\Service\WorkspaceLayoutService;
use Wexample\SymfonyWorkspace\Service\WorkspaceWidgetRegistry;

/**
 * What a page hands a workspace board:
 *
 *   {{ vue(render_pass, '@WexampleSymfonyWorkspaceBundle/components/workspace-board',
 *       workspace_board('project-dashboard', { subject: { entityName: 'project', id: project.id },
 *         widgets: ['traffic', 'errors'], layout: default_tree })) }}
 *
 * The board requires the vues of the widgets it is handed, so a widget it
 * offers is one it can draw.
 */
class WorkspaceExtension extends AbstractExtension
{
    public function __construct(
        private readonly WorkspaceWidgetRegistry $widgetRegistry,
        private readonly WorkspaceLayoutService $layoutService,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('workspace_board', $this->workspaceBoard(...)),
        ];
    }

    /**
     * The props of a board: its key, what it is about, the widgets it offers,
     * the arrangement the account left — or the one offered to everyone, or
     * the page's `layout` —, and where to keep it when there is an account.
     */
    public function workspaceBoard(string $layoutKey, array $options = []): array
    {
        $subject = $options['subject'] ?? null;
        $subjectType = $subject['entityName'] ?? null;
        $subjectId = isset($subject['id']) ? (string) $subject['id'] : null;
        $user = $this->security->getUser();
        $owner = $user instanceof UserEntityInterface ? $user : null;

        return [
            'layoutKey' => $layoutKey,
            'subject' => $subject,
            'widgets' => $this->widgetRegistry->describe(
                $this->widgetRegistry->getWidgets($options['widgets'] ?? null)
            ),
            // The bars its sections may carry, the page's own: `{ name, label,
            // icon, view }`, the vue handed `values`, `subject`, `context`,
            // `workspace` and `node-id`.
            'toolbars' => $this->widgetRegistry->describeToolbars($options['toolbars'] ?? []),
            'layout' => $this->layoutService->findTree($layoutKey, $subjectType, $subjectId, $owner)
                ?? ($options['layout'] ?? null),
            'saveUrl' => $owner
                ? $this->urlGenerator->generate('workspace_layout_'.WorkspaceLayoutController::ROUTE_SAVE)
                : null,
            'saveToken' => $owner
                ? $this->csrfTokenManager->getToken(WorkspaceLayoutController::CSRF_TOKEN_ID)->getValue()
                : null,
            'flush' => $options['flush'] ?? false,
            'live' => $options['live'] ?? false,
            // What the page hands every widget: addresses, ids, the props of
            // what a widget draws of the page's own.
            'context' => $options['context'] ?? [],
        ];
    }
}
