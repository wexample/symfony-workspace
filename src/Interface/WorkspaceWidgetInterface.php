<?php

namespace Wexample\SymfonyWorkspace\Interface;

/**
 * What a zone of a workspace may show: a vue, under a name the saved
 * arrangement keeps. Any service implementing it is offered — a bundle ships
 * its widgets as it ships its pages.
 *
 * The vue receives `config` (what this zone keeps of it), `subject` (the
 * record the zone, or a section above it, is about, `{ entityName, id }`) and
 * `workspace` (the board: `emit(type, detail)`, `on(type, listener)`,
 * `updateConfig(zoneId, config)`), `context` (what the page hands every
 * widget: addresses, ids), `scope` (what the bars of the sections above it
 * hold — a session chosen once for the widgets under it) and `zone-id`.
 */
interface WorkspaceWidgetInterface
{
    public const string TAG = 'wexample_symfony_workspace.widget';

    /** What the arrangement stores: `traffic-chart`. Never renamed once saved. */
    public function getName(): string;

    /** A translation key, or the words themselves. */
    public function getLabel(): string;

    public function getDescription(): ?string;

    /** An icon of the design system: `ph:bold/chart-line`. */
    public function getIcon(): ?string;

    /** The vue drawing it: `@AppBundle/vue/workspace/traffic-chart`. */
    public function getView(): string;
}
