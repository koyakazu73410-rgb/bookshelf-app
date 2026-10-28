<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookUpdateValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_update_rejects_missing_required_fields_without_changing_book(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create(['title' => '更新前']);

        $this->actingAs($owner)->put(route('books.update', $book), [])
            ->assertSessionHasErrors(['title', 'author', 'genres'])
            ->assertSessionDoesntHaveErrors(['isbn', 'published_date']);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '更新前']);
    }

    public function test_book_update_rejects_invalid_date_url_and_genres(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create(['title' => '更新前']);
        $genre = Genre::factory()->create();

        $this->actingAs($owner)->put(route('books.update', $book), [
            'title' => '更新後',
            'author' => '著者',
            'isbn' => $book->isbn,
            'published_date' => 'invalid-date',
            'image_url' => 'invalid-url',
            'genres' => [$genre->id, $genre->id, 999999],
        ])->assertSessionHasErrors([
            'published_date', 'image_url', 'genres.1', 'genres.2',
        ]);

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => '更新前']);
    }
}
