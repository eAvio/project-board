<?php

namespace Eavio\ProjectBoard;

use Laravel\Nova\ResourceTool;

class ProjectBoardResourceTool extends ResourceTool
{
    /**
     * Get the displayable name of the resource tool.
     *
     * @return string
     */
    public function name()
    {
        return 'Projects Board';
    }

    /**
     * Get the component name for the resource tool.
     *
     * @return string
     */
    public function component()
    {
        return 'projects-board-resource-tool';
    }

    /**
     * Prepare the element for JSON serialization.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'currentUser' => request()->user(),
        ]);
    }
}
