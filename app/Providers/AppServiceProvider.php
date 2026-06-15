<?php

namespace App\Providers;

use App\Contracts\Knowledge\KnowledgeRetrievalContract;
use App\Services\Knowledge\PublishedKnowledgeSearchService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(KnowledgeRetrievalContract::class, PublishedKnowledgeSearchService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
