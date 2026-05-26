<?php

namespace App\Providers;

use App\Models\Item;
use App\Models\User;
use App\Policies\ItemPolicy;
use App\Policies\UserPolicy;
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
        Gate::policy(User::class, UserPolicy::class);
    }
}
