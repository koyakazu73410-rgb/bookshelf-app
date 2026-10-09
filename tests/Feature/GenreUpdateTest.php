<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_genre(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $genre = Genre::factory()->create([
        'name' => '旧ジャンル名',
        ]);

        $data = [
            'name' => 'プログラミング',
        ];

        $response = $this->put(
            route('genres.update', $genre),
            $data
        );

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => 'プログラミング',
        ]);

        $response->assertRedirect(route('genres.index'));
    }
}
