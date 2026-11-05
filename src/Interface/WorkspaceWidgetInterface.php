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
 * widget: addresses, ids), `scope` (what the board's bar holds — a record
 * chosen once for all its widgets), `zone-id` and `alone` (shown by itself —
 * a zone in its own window, a widget opened in a panel —, no other zone
 * beside it: what only matters among others, a pin, is left out).
 *
 * Every widget stands in the same frame, a zone that scrolls, and draws its
 * root as a `zone--content` of the design system — the zone's room round it
 * —, or `zone--content zone--content--flush page--fill` when it fills the
 * frame edge to edge and insets itself (a stage, a thread). A widget whose
 * content is set by a few controls — a preset, a mode — heads it with the
 * zone's own bar, `toolbar toolbar--compact zone--head`, its root then flush
 * and its body a `zone--content` under the bar. No room of its
 * own: every widget reads alike, and alike once opened in a window of its
 * own (`?workspace_zone={id}`, the board's bar over it alone).
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
