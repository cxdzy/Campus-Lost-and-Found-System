<?php

namespace App\Providers;

use App\Models\Item;
use App\Policies\ItemPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::policy(Item::class, ItemPolicy::class);
    }
}
