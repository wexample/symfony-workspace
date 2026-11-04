<?php

namespace Wexample\SymfonyWorkspace\Interface;

/**
 * A widget that sets the size of its zone itself, one way or both: the zone
 * takes what the widget's content measures that way — growing and shrinking
 * with it, a mode switched, a panel unfolded —, and the reader can no longer
 * lock it there.
 */
interface WorkspaceWidgetSizingInterface
{
    public const string SIZING_CONTENT = 'content';

    /**
     * Per axis — `width`, `height` —, how the widget sizes its zone:
     * `['height' => self::SIZING_CONTENT]`. An axis left out is the reader's.
     *
     * @return array<string, string>
     */
    public function getSizing(): array;
}
