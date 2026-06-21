<?php

namespace App\Providers;

use App\Contracts\Knowledge\KnowledgeRetrievalContract;
use App\Services\Auth\PostLoginRedirect;
use App\Services\Billing\EntitlementResolver;
use App\Services\Knowledge\PublishedKnowledgeSearchService;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(KnowledgeRetrievalContract::class, PublishedKnowledgeSearchService::class);
        $this->app->singleton(EntitlementResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Route authenticated users hitting guest-only routes (e.g. /login) to their
        // role-based destination instead of the public landing page.
        RedirectIfAuthenticated::redirectUsing(function () {
            $user = Auth::user();

            if ($user === null) {
                return route('home');
            }

            return app(PostLoginRedirect::class)->intendedUrl($user);
        });
    }
}
