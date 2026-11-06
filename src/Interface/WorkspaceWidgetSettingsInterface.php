<?php

namespace Wexample\SymfonyWorkspace\Interface;

/**
 * A widget with settings of its own — what it shows, how —, set in its zone
 * while the board is arranged: a form of the application, drawn under the
 * widget chosen, its processor validating what is sent. What it validates is
 * kept with the zone (`config.settings`), the same for everyone who opens
 * the page; the widget reads it there, its defaults where nothing is set.
 *
 * Its form is drawn by symfony-forms (`form_definition_for`).
 */
interface WorkspaceWidgetSettingsInterface
{
    /**
     * The form setting it, a form with a processor: `TrafficSettingsForm::class`.
     *
     * @return class-string
     */
    public function getSettingsForm(): string;
}
