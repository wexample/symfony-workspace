<?php

namespace Wexample\SymfonyWorkspace\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Wexample\SymfonyLoader\Helper\ComponentPathHelper;
use Wexample\SymfonyLoader\Twig\VueExtension;
use Wexample\SymfonyHelpers\Attribute\WorkerSafe;
use Wexample\SymfonyWorkspace\Interface\WorkspaceWidgetInterface;
use Wexample\SymfonyWorkspace\Interface\WorkspaceWidgetSizingInterface;

/**
 * The widgets a workspace may show, collected from every bundle. A page asks
 * for some of them by name, or for all; the browser receives what it needs to
 * offer them and draw them, and nothing it cannot draw: a vue the page did not
 * require has no template to mount.
 */
class WorkspaceWidgetRegistry
{
    /** @var array<string, WorkspaceWidgetInterface> */
    #[WorkerSafe('The widgets the bundles declare, the same for every request.')]
    private array $widgets = [];

    public function __construct(
        #[AutowireIterator(WorkspaceWidgetInterface::TAG)]
        iterable $widgets,
        private readonly TranslatorInterface $translator,
        private readonly Environment $twig,
    ) {
        foreach ($widgets as $widget) {
            $this->widgets[$widget->getName()] = $widget;
        }
    }

    /**
     * @param string[]|null $names Those offered, in that order; null for all.
     *
     * @return WorkspaceWidgetInterface[]
     */
    public function getWidgets(?array $names = null): array
    {
        if ($names === null) {
            return array_values($this->widgets);
        }

        return array_values(array_filter(array_map(
            fn (string $name) => $this->widgets[$name] ?? null,
            $names
        )));
    }

    public function getWidget(string $name): ?WorkspaceWidgetInterface
    {
        return $this->widgets[$name] ?? null;
    }

    /**
     * The bar over a board, as the browser is told it: `{ view }`, its vue
     * resolved.
     */
    public function describeToolbar(?array $toolbar): ?array
    {
        return $toolbar ? [
            'view' => ComponentPathHelper::resolveView(
                $this->twig,
                $toolbar['view'],
                VueExtension::TEMPLATE_FILE_EXTENSION
            ),
        ] : null;
    }

    /**
     * What the browser is told of each: its name, its words in the page's
     * language, its icon, its vue — and how it sizes its zone, when it does
     * (WorkspaceWidgetSizingInterface).
     *
     * @param WorkspaceWidgetInterface[] $widgets
     */
    public function describe(array $widgets): array
    {
        return array_map(fn (WorkspaceWidgetInterface $widget) => [
            'name' => $widget->getName(),
            'label' => $this->translator->trans($widget->getLabel()),
            'description' => $widget->getDescription() !== null
                ? $this->translator->trans($widget->getDescription())
                : null,
            'icon' => $widget->getIcon(),
            // As the loader registers it — `components/traffic` is the vue of
            // `components/traffic/traffic` —, the name the browser finds it by.
            'view' => ComponentPathHelper::resolveView(
                $this->twig,
                $widget->getView(),
                VueExtension::TEMPLATE_FILE_EXTENSION
            ),
            'sizing' => $widget instanceof WorkspaceWidgetSizingInterface ? $widget->getSizing() : null,
        ], $widgets);
    }
}
