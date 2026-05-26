<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_submit_requires_image_url_and_caption(): void
    {
        $this->postJson('/api/bot/submit', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image_url', 'caption']);
    }

    public function test_bot_submit_returns_ai_analysis(): void
    {
        $payload = [
            'image_url' => 'https://example.com/image.jpg',
            'caption' => 'Found near the library',
        ];

        $response = $this->postJson('/api/bot/submit', $payload)
            ->assertOk()
            ->assertJson([
                'message' => 'Bot submission saved',
                'analysis' => [
                    'category' => 'electronics',
                    'confidence' => 0.95,
                    'description' => 'A black smartphone',
                ],
            ]);

        $this->assertDatabaseCount('items', 1);
        $this->assertNotNull($response->json('id'));
    }
}
