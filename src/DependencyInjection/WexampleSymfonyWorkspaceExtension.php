<?php

namespace Wexample\SymfonyWorkspace\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;
use Wexample\SymfonyWorkspace\Interface\WorkspaceWidgetInterface;

class WexampleSymfonyWorkspaceExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $this->loadConfig(
            __DIR__,
            $container
        );

        // A service implementing the interface is a widget a workspace may
        // show, whichever bundle ships it: the tag the registry collects follows.
        $container
            ->registerForAutoconfiguration(WorkspaceWidgetInterface::class)
            ->addTag(WorkspaceWidgetInterface::TAG);
    }
}
