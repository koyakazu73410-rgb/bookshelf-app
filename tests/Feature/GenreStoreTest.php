<?php

namespace Tests\Feature;

use App\Models\user;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class GenreStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_store_genre(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $data = [
            'name' => 'プログラミング',
        ];

        $response = $this->post(
            route('genres.store'),
            $data
        );

        $this->assertDatabaseHas('genres', [
            'name' => 'プログラミング',
        ]);

        $response->assertRedirect(route('genres.index'));
    }
}
