<?php

namespace Wexample\SymfonyWorkspace\Interface;

/**
 * A widget the board opens in a window of its own when an event it shows is
 * told and no zone takes it: a slot's id pointed at on a board without the
 * slot's editor, the editor opened on the slot. The window shows the widget
 * alone and hands it the event as its zone would have heard it.
 */
interface WorkspaceWidgetOpeningInterface
{
    /**
     * The events it is opened by, as the widgets tell them: `['slot:selected']`.
     *
     * @return string[]
     */
    public function getOpeningEvents(): array;
}
