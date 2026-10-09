<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class GenreDeleteTest extends TestCase
{
    use RefreshDatabase;
    public function test_authenticated_user_can_delete_genre(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $genre = Genre::factory()->create([
            'name' => '削除するジャンル',
        ]);

        $response = $this->delete(
            route('genres.destroy', $genre)
        );

        $this->assertDatabaseMissing('genres', [
            'id' => $genre->id,
        ]);

        $response->assertRedirect(route('genres.index'));
    }
}
