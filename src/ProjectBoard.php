<?php

namespace Eavio\ProjectBoard;

use Illuminate\Http\Request;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;

class ProjectBoard extends Tool
{
    /**
     * Perform any tasks that need to happen when the tool is booted.
     */
    public function boot(): void
    {
        // Register compiled assets for the tool and resource tool
        // This mirrors the working setup from SportPlus (Nova 5)
        Nova::script('project-board', __DIR__.'/../dist/js/tool.js');
        Nova::style('project-board', __DIR__.'/../dist/css/tool.css');
    }

    public function authorize(Request $request): bool
    {
        // Allow all authenticated Nova users to access the tool's routes
        // Sidebar visibility is still controlled via canSee below
        return (bool) $request->user();
    }

    /**
     * Build the menu that renders the navigation links for the tool.
     */
    public function menu(Request $request): MenuSection
    {
        return MenuSection::make('Projects Board')
            ->path('/project-board')
            ->icon('server')
            ->canSee(function () use ($request) {
                $user = $request->user();

                return $user && method_exists($user, 'isSuperAdmin')
                    ? $user->isSuperAdmin()
                    : false;
            });
    }
}
