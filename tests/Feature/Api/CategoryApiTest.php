<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_requires_authentication(): void
    {
        $this->getJson('/api/categories')
            ->assertStatus(401);
    }

    public function test_categories_returns_list_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        Category::create([
            'category_name' => 'Electronics',
            'icon_identifier' => 'electronics',
        ]);
        Category::create([
            'category_name' => 'Wallets',
            'icon_identifier' => 'wallet',
        ]);

        $this->actingAs($user)
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
