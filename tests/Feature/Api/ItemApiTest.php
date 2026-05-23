<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_requires_authentication(): void
    {
        $this->getJson('/api/items')
            ->assertStatus(401);
    }

    public function test_authenticated_user_can_list_items(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'category_name' => 'Electronics',
            'icon_identifier' => 'electronics',
        ]);

        $item = Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'type' => 'Lost',
            'title_description' => 'Black laptop bag',
            'latitude' => 3.0712,
            'longitude' => 101.4984,
            'location_name' => 'Library',
            'image_path' => 'items/laptop-bag.jpg',
            'status' => 'Pending',
        ]);

        $this->actingAs($user)
            ->getJson('/api/items')
            ->assertOk()
            ->assertJsonPath('data.0.id', $item->id);
    }

    public function test_authenticated_user_can_create_item(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'category_name' => 'Electronics',
            'icon_identifier' => 'electronics',
        ]);

        $payload = [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'type' => 'Found',
            'title_description' => 'Silver USB drive',
            'latitude' => 3.0712,
            'longitude' => 101.4984,
            'location_name' => 'Cafeteria',
            'image_path' => 'items/usb-drive.jpg',
        ];

        $this->actingAs($user)
            ->postJson('/api/items', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'Pending');

        $this->assertDatabaseHas('items', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title_description' => 'Silver USB drive',
            'status' => 'Pending',
        ]);
    }

    public function test_authenticated_user_can_update_item(): void
    {
        $user = User::factory()->create();
        $category = Category::create([
            'category_name' => 'Electronics',
            'icon_identifier' => 'electronics',
        ]);

        $item = Item::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'type' => 'Lost',
            'title_description' => 'Black umbrella',
            'latitude' => 3.0712,
            'longitude' => 101.4984,
            'location_name' => 'Lecture Hall',
            'image_path' => 'items/umbrella.jpg',
            'status' => 'Pending',
        ]);

        $this->actingAs($user)
            ->patchJson("/api/items/{$item->id}", [
                'status' => 'Matched',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Matched');

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'status' => 'Matched',
        ]);
    }
}
