<?php

namespace App\Providers;

use App\Models\ProcurementItem;
use App\Policies\ProcurementItemPolicy;
use Illuminate\Support\Facades\Gate;
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
        // Register policies for authorization
        Gate::policy(ProcurementItem::class, ProcurementItemPolicy::class);
    }
}
