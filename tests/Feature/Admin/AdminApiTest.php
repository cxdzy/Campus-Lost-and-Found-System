<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_cannot_manage_users(): void
    {
        $user = User::factory()->create(['role' => 'Security']);

        $this->actingAs($user)
            ->getJson('/admin/api/users')
            ->assertStatus(403);
    }

    public function test_admin_can_create_update_and_delete_users(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);

        $created = $this->actingAs($admin)
            ->postJson('/admin/api/users', [
                'name' => 'New Staff',
                'matric_number' => 'A7654321',
                'role' => 'Security',
                'telegram_chat_id' => 'telegram_123',
                'password' => 'secret123',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertDatabaseHas('users', [
            'matric_number' => 'A7654321',
            'role' => 'Security',
        ]);

        $this->actingAs($admin)
            ->patchJson('/admin/api/users/'.$created['id'], [
                'name' => 'Updated Staff',
                'role' => 'Admin',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Staff')
            ->assertJsonPath('data.role', 'Admin');

        $this->actingAs($admin)
            ->deleteJson('/admin/api/users/'.$created['id'])
            ->assertNoContent();

        $this->assertDatabaseMissing('users', [
            'matric_number' => 'A7654321',
        ]);
    }

    public function test_admin_can_create_update_and_delete_reports(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'Admin']);
        $reporter = User::factory()->create(['role' => 'Security']);
        $category = Category::create([
            'category_name' => 'Electronics',
            'icon_identifier' => 'electronics',
        ]);

        $created = $this->actingAs($admin)
            ->post('/admin/api/reports', [
                'user_id' => $reporter->id,
                'category_id' => $category->id,
                'type' => 'Found',
                'title_description' => 'Silver USB drive',
                'latitude' => 3.0712,
                'longitude' => 101.4984,
                'location_name' => 'Cafeteria',
                'status' => 'Pending',
                'image_file' => UploadedFile::fake()->create('usb-drive.jpg', 64, 'image/jpeg'),
            ])
            ->assertCreated()
            ->json('data');

        $this->assertDatabaseHas('items', [
            'id' => $created['id'],
            'title_description' => 'Silver USB drive',
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->patch('/admin/api/reports/'.$created['id'], [
                '_method' => 'PATCH',
                'status' => 'Claimed',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Claimed');

        $this->actingAs($admin)
            ->deleteJson('/admin/api/reports/'.$created['id'])
            ->assertNoContent();

        $this->assertDatabaseMissing('items', [
            'id' => $created['id'],
        ]);
    }

    public function test_found_report_requires_image(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $reporter = User::factory()->create(['role' => 'Security']);
        $category = Category::create([
            'category_name' => 'Electronics',
            'icon_identifier' => 'electronics',
        ]);

        $this->actingAs($admin)
            ->postJson('/admin/api/reports', [
                'user_id' => $reporter->id,
                'category_id' => $category->id,
                'type' => 'Found',
                'title_description' => 'Silver USB drive',
                'latitude' => 3.0712,
                'longitude' => 101.4984,
                'location_name' => 'Cafeteria',
                'status' => 'Pending',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image_file']);
    }
}
