<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookNullableFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_book_can_be_created_without_isbn_and_published_date(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => '任意項目なしの書籍',
            'author' => '著者名',
            'genres' => [$genre->id],
        ]);

        $book = Book::where('title', '任意項目なしの書籍')->firstOrFail();
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('books.show', $book));
        $this->assertNull($book->isbn);
        $this->assertNull($book->published_date);
    }

    public function test_web_book_can_be_updated_to_clear_isbn_and_published_date(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $genre = Genre::factory()->create();

        $response = $this->actingAs($owner)->put(route('books.update', $book), [
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => null,
            'published_date' => null,
            'genres' => [$genre->id],
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('books.show', $book));
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'isbn' => null,
            'published_date' => null,
        ]);
    }

    public function test_api_book_can_be_created_without_isbn_and_published_date(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/books', [
            'title' => 'API任意項目なしの書籍',
            'author' => 'API著者',
            'genres' => [$genre->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.isbn', null)
            ->assertJsonPath('data.published_date', null);
        $this->assertDatabaseHas('books', [
            'title' => 'API任意項目なしの書籍',
            'isbn' => null,
            'published_date' => null,
        ]);
    }

    public function test_api_book_can_be_updated_to_clear_isbn_and_published_date(): void
    {
        $book = Book::factory()->create();
        $genre = Genre::factory()->create();
        Sanctum::actingAs($book->user);

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => null,
            'published_date' => null,
            'genres' => [$genre->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.isbn', null)
            ->assertJsonPath('data.published_date', null);
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'isbn' => null,
            'published_date' => null,
        ]);
    }

    public function test_optional_fields_are_still_validated_when_provided(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->actingAs($user)->post(route('books.store'), [
            'title' => '形式不正の書籍',
            'author' => '著者名',
            'isbn' => '123',
            'published_date' => 'invalid-date',
            'genres' => [$genre->id],
        ])->assertSessionHasErrors([
            'isbn' => 'ISBNは13桁の数字で入力してください。',
            'published_date' => '正しい日付を入力してください。',
        ]);

        $this->assertDatabaseMissing('books', ['title' => '形式不正の書籍']);
    }
}
