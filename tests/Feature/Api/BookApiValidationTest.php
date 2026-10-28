<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookApiValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_rejects_invalid_search_filter_and_pagination_parameters(): void
    {
        $response = $this->getJson('/api/v1/books?keyword[]=x&genre_id=999&page=0&per_page=101');

        $response->assertUnprocessable()
            ->assertJsonPath('message', '入力内容に誤りがあります。')
            ->assertJsonValidationErrors(['keyword', 'genre_id', 'page', 'per_page']);
    }

    public function test_store_rejects_missing_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/books', []);

        $response->assertUnprocessable()
            ->assertJsonPath('message', '入力内容に誤りがあります。')
            ->assertJsonValidationErrors([
                'title', 'author', 'genres',
            ])
            ->assertJsonMissingValidationErrors(['isbn', 'published_date']);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_store_rejects_invalid_values_and_duplicate_genres(): void
    {
        $genre = Genre::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/books', [
            'title' => 'API書籍',
            'author' => 'API著者',
            'isbn' => '123',
            'published_date' => 'invalid-date',
            'description' => ['invalid'],
            'image_url' => 'invalid-url',
            'genres' => [$genre->id, $genre->id],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'isbn', 'published_date', 'description', 'image_url', 'genres.1',
            ]);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_store_rejects_duplicate_isbn(): void
    {
        $book = Book::factory()->create(['isbn' => '9781234567890']);
        $genre = Genre::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/books', [
            'title' => 'ISBN重複',
            'author' => 'API著者',
            'isbn' => $book->isbn,
            'published_date' => '2025-01-01',
            'genres' => [$genre->id],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('isbn');
        $this->assertDatabaseCount('books', 1);
    }

    public function test_show_update_and_delete_return_json_404_for_missing_book(): void
    {
        $this->getJson('/api/v1/books/999999')
            ->assertNotFound()
            ->assertExactJson(['error' => '書籍が見つかりません。']);

        Sanctum::actingAs(User::factory()->create());

        foreach (['putJson', 'deleteJson'] as $method) {
            $response = $this->{$method}('/api/v1/books/999999');

            $response->assertNotFound()
                ->assertExactJson(['error' => '書籍が見つかりません。']);
        }
    }

    public function test_update_rejects_another_books_isbn(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create(['isbn' => '9781234567890']);
        $otherBook = Book::factory()->create(['isbn' => '9780987654321']);
        $genre = Genre::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '更新API書籍',
            'author' => '更新著者',
            'isbn' => $otherBook->isbn,
            'published_date' => '2025-01-01',
            'genres' => [$genre->id],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('isbn');
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'isbn' => '9781234567890',
        ]);
    }
}
