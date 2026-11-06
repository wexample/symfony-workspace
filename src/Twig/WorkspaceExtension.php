<?php

namespace Wexample\SymfonyWorkspace\Twig;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
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
 * `layoutSubject` keeps the arrangement under something else than the
 * subject — `{ entityName: 'scenario_lineage', id: scenario.lineage }`: one
 * arrangement for every version of a record.
 *
 * The board requires the vues of the widgets it is handed, so a widget it
 * offers is one it can draw.
 *
 * A page asked with `workspace_widget` — the board opening, in a panel, the
 * widget an event no zone took opens — draws that widget alone, handed the
 * event (`workspace_event`, `workspace_detail` as JSON).
 */
class WorkspaceExtension extends AbstractExtension
{
    public const string PARAMETER_WIDGET = 'workspace_widget';
    public const string PARAMETER_EVENT = 'workspace_event';
    public const string PARAMETER_DETAIL = 'workspace_detail';

    public function __construct(
        private readonly RequestStack $requestStack,
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
     * the arrangement chosen last — or the page's `layout` —, and where to
     * keep it when an account is signed in.
     */
    public function workspaceBoard(string $layoutKey, array $options = []): array
    {
        $subject = $options['subject'] ?? null;
        // What the arrangement is kept under, when it is not the subject: a
        // record's lineage rather than one version of it, so that every
        // version opens on the same arrangement (`layoutSubject`).
        $layoutSubject = $options['layoutSubject'] ?? $subject;
        $subjectType = $layoutSubject['entityName'] ?? null;
        $subjectId = isset($layoutSubject['id']) ? (string) $layoutSubject['id'] : null;
        $signedIn = $this->security->getUser() instanceof UserEntityInterface;
        $current = $this->layoutService->findCurrent($layoutKey, $subjectType, $subjectId);

        return [
            'layoutKey' => $layoutKey,
            'subject' => $subject,
            'layoutSubject' => $layoutSubject,
            'widgets' => $this->widgetRegistry->describe(
                $this->widgetRegistry->getWidgets($options['widgets'] ?? null)
            ),
            // The bar over the board, the page's own (`{ view }`): its vue is
            // handed `values`, `subject`, `context` and `workspace`.
            'toolbar' => $this->widgetRegistry->describeToolbar($options['toolbar'] ?? null),
            'layout' => $current?->getTree() ?? ($options['layout'] ?? null),
            // The page's own arrangement, what a new one starts from.
            'defaultLayout' => $options['layout'] ?? null,
            'saveUrl' => $signedIn
                ? $this->urlGenerator->generate('workspace_layout_'.WorkspaceLayoutController::ROUTE_SAVE)
                : null,
            // The arrangements the page keeps — the one shown among them —,
            // and where they are made, chosen and renamed.
            'layouts' => $signedIn ? $this->layoutService->describeAll($layoutKey, $subjectType, $subjectId) : [],
            'layoutId' => $current ? (string) $current->getId() : null,
            'layoutUrls' => $signedIn ? [
                'create' => $this->urlGenerator->generate('workspace_layout_'.WorkspaceLayoutController::ROUTE_CREATE),
                'select' => $this->urlGenerator->generate('workspace_layout_'.WorkspaceLayoutController::ROUTE_SELECT),
                'rename' => $this->urlGenerator->generate('workspace_layout_'.WorkspaceLayoutController::ROUTE_RENAME),
            ] : null,
            'saveToken' => $signedIn
                ? $this->csrfTokenManager->getToken(WorkspaceLayoutController::CSRF_TOKEN_ID)->getValue()
                : null,
            'flush' => $options['flush'] ?? false,
            'live' => $options['live'] ?? false,
            // What the page hands every widget: addresses, ids, the props of
            // what a widget draws of the page's own.
            'context' => $options['context'] ?? [],
            'opened' => $this->opened(),
        ];
    }

    /**
     * The widget the page is asked to draw alone, and the event it is handed:
     * `{ widget, type, detail }`, or none.
     */
    private function opened(): ?array
    {
        $query = $this->requestStack->getCurrentRequest()?->query;
        $widget = $query?->get(self::PARAMETER_WIDGET);

        if (! is_string($widget) || $widget === '') {
            return null;
        }

        $type = $query->get(self::PARAMETER_EVENT);
        $detail = $query->get(self::PARAMETER_DETAIL);

        return [
            'widget' => $widget,
            'type' => is_string($type) && $type !== '' ? $type : null,
            'detail' => is_string($detail) ? json_decode($detail, true) : null,
        ];
    }
}
