<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel's stock pagination views are Tailwind-only. Registering the
        // design-system view as the default converts every paginated page in
        // the app -- the seven admin index pages and the public events list --
        // without touching a single call site.
        Paginator::defaultView('vendor.pagination.design-system');
        Paginator::defaultSimpleView('vendor.pagination.design-system');
    }
}
