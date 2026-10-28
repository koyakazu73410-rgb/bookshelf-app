<?php

namespace Tests\Feature;

use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreValidationAdditionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_genre_update_rejects_name_used_by_another_genre(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '変更前']);
        Genre::factory()->create(['name' => '既存ジャンル']);

        $this->actingAs($user)->put(route('genres.update', $genre), [
            'name' => '既存ジャンル',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => '変更前']);
    }

    public function test_genre_store_rejects_non_string_and_overlong_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('genres.store'), ['name' => ['配列']])
            ->assertSessionHasErrors('name');

        $this->actingAs($user)->post(route('genres.store'), [
            'name' => str_repeat('あ', 256),
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('genres', 0);
    }
}
