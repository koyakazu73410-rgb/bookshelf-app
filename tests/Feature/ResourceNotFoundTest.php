<?php

namespace Tests\Feature;

use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceNotFoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_book_routes_return_404(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->get('/books/999999')->assertNotFound();
        $this->actingAs($user)->get('/books/999999/edit')->assertNotFound();
        $this->actingAs($user)->put('/books/999999', [
            'title' => '存在しない書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567890',
            'published_date' => '2025-01-01',
            'genres' => [$genre->id],
        ])->assertNotFound();
        $this->actingAs($user)->delete('/books/999999')->assertNotFound();
    }

    public function test_missing_review_routes_return_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/reviews/999999/edit')->assertNotFound();
        $this->actingAs($user)->put('/reviews/999999', [])->assertNotFound();
        $this->actingAs($user)->delete('/reviews/999999')->assertNotFound();
        $this->actingAs($user)->post('/reviews/999999/like')->assertNotFound();
    }

    public function test_missing_genre_routes_return_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/genres/999999')->assertNotFound();
        $this->actingAs($user)->get('/genres/999999/edit')->assertNotFound();
        $this->actingAs($user)->put('/genres/999999', [])->assertNotFound();
        $this->actingAs($user)->delete('/genres/999999')->assertNotFound();
    }
}
