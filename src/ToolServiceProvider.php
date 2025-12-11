<?php

namespace Eavio\ProjectBoard;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;
use Eavio\ProjectBoard\Http\Middleware\Authorize;
use Eavio\ProjectBoard\Http\Middleware\AuthenticateApiToken;
use Laravel\Nova\Http\Middleware\Authenticate;

class ToolServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            $this->routes();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'project-board');

        // Register middleware alias
        $this->app['router']->aliasMiddleware('project-board.api-token', AuthenticateApiToken::class);

        // Publish config
        $this->publishes([
            __DIR__.'/../config/project-board.php' => config_path('project-board.php'),
        ], 'project-board-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'project-board-migrations');

        Nova::serving(function (ServingNova $event) {
            Nova::script('project-board', __DIR__.'/../dist/js/tool.js');
            Nova::style('project-board', __DIR__.'/../dist/css/tool.css');
        });
    }

    /**
     * Register the tool's routes.
     */
    protected function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        // Nova UI routes
        Nova::router(['nova', Authenticate::class, Authorize::class], 'project-board')
            ->group(__DIR__.'/../routes/inertia.php');

        // Nova API routes (for the tool UI)
        Route::middleware(['nova', Authenticate::class, Authorize::class])
            ->prefix('nova-vendor/project-board')
            ->group(__DIR__.'/../routes/api.php');

        // External API routes (for ChatGPT, integrations, etc.)
        Route::middleware(['api'])
            ->prefix('api/project-board')
            ->group(__DIR__.'/../routes/external-api.php');

        // Token display route (web, no auth - uses one-time cache key)
        Route::middleware(['web'])
            ->group(__DIR__.'/../routes/web.php');
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/project-board.php', 'project-board');
    }
}
