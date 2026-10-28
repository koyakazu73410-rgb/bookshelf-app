<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_store_rejects_missing_required_fields(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('books.store'), []);

        $response->assertSessionHasErrors([
            'title', 'author', 'genres',
        ]);
        $response->assertSessionDoesntHaveErrors(['isbn', 'published_date']);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_book_store_rejects_invalid_values_and_duplicate_genres(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->post(route('books.store'), [
                'title' => 'テスト書籍',
                'author' => 'テスト著者',
                'isbn' => '123456789012',
                'published_date' => 'not-a-date',
                'description' => ['invalid'],
                'image_url' => 'not-a-url',
                'genres' => [$genre->id, $genre->id],
            ]);

        $response->assertSessionHasErrors([
            'isbn', 'published_date', 'description', 'image_url', 'genres.1',
        ]);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_book_store_rejects_duplicate_isbn(): void
    {
        $book = Book::factory()->create(['isbn' => '9781234567890']);
        $genre = Genre::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->post(route('books.store'), [
                'title' => '重複ISBN書籍',
                'author' => 'テスト著者',
                'isbn' => $book->isbn,
                'published_date' => '2025-01-01',
                'genres' => [$genre->id],
            ]);

        $response->assertSessionHasErrors('isbn');
        $this->assertDatabaseCount('books', 1);
    }

    public function test_book_update_rejects_another_books_isbn(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create(['isbn' => '9781234567890']);
        $otherBook = Book::factory()->create(['isbn' => '9780987654321']);
        $genre = Genre::factory()->create();

        $response = $this->actingAs($owner)
            ->put(route('books.update', $book), [
                'title' => '更新書籍',
                'author' => '更新著者',
                'isbn' => $otherBook->isbn,
                'published_date' => '2025-01-01',
                'genres' => [$genre->id],
            ]);

        $response->assertSessionHasErrors('isbn');
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'isbn' => '9781234567890',
        ]);
    }
}
