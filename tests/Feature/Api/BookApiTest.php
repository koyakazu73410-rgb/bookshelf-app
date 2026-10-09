<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Genre;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_books_can_be_retrieved_from_api(): void
    {
        $book = Book::factory()->create([
            'title' => 'Laravel入門',
        ]);

        $response = $this->getJson('/api/v1/books');

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'title' => 'Laravel入門',
        ]);
    }

    public function test_book_can_be_retrieved_from_api(): void
    {
        $book = Book::factory()->create([
            'title' => 'Laravel実践',
        ]);

        $response = $this->getJson('/api/v1/books/' . $book->id);

        $response->assertStatus(200);

        $response->assertJsonFragment([
            'title' => 'Laravel実践',
        ]);
    }

    public function test_book_can_be_created_from_api(): void
    {
        $user = User::factory()->create();


        $genre = Genre::factory()->create();

        $data = Book::factory()->make()->only([
            'title',
            'author',
            'isbn',
            'published_date',
            'description',
            'image_url',
        ]);

        $data['user_id'] = $user->id;
        $data['published_date'] = '2025-01-01';
        $data['genres'] = [$genre->id];

        $response = $this->postJson('/api/v1/books', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('books', [
            'title' => $data['title'],
            'author' => $data['author'],
            'isbn' => $data['isbn'],
        ]);
    }

    public function test_book_can_be_updated_from_api(): void
    {
        $book = Book::factory()->create([
            'title' => '更新前の書籍',
        ]);

        $genre = Genre::factory()->create();

        $data = [
            'user_id' => $book->user_id,
            'title' => '更新後の書籍',
            'author' => $book->author,
            'isbn' => $book->isbn,
            'published_date' => $book->published_date,
            'genres' => [$genre->id],
        ];

        $response = $this->putJson('/api/v1/books/' . $book->id, $data);

        $response->assertStatus(200);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新後の書籍',
        ]);
    }

    public function test_book_can_be_deleted_from_api(): void
    {
        $book = Book::factory()->create([
            'title' => '削除対象の書籍',
        ]);

        $response = $this->deleteJson('/api/v1/books/' . $book->id);

        $response->assertNoContent();

        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
        ]);
    }
}
