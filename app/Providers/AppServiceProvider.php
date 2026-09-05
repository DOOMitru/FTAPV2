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

        // defaultSimpleView is deliberately NOT set to the same view. That view
        // windows page numbers, so it asks the paginator for total() and
        // lastPage() -- neither of which exists on a simple paginator, which
        // knows only whether there is another page. Registering it here made
        // the first ever call to simplePaginate() a fatal error instead of a
        // page, and nothing in the app calls it, so the fault would have been
        // discovered by whoever first tried rather than by anyone who could
        // have prevented it.
        //
        // Left at Laravel's stock view: unstyled, but working. A design-system
        // previous/next view is easy enough to add the day something actually
        // needs one, and building it now is a view for a caller that does not
        // exist.
    }
}
