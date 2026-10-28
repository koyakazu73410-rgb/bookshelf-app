<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_book_write_endpoints(): void
    {
        $book = Book::factory()->create();

        $responses = [
            $this->postJson('/api/v1/books', []),
            $this->putJson("/api/v1/books/{$book->id}", []),
            $this->deleteJson("/api/v1/books/{$book->id}"),
        ];

        foreach ($responses as $response) {
            $response->assertUnauthorized()
                ->assertHeader('content-type', 'application/json')
                ->assertJsonStructure(['message']);
        }
    }

    public function test_authenticated_user_can_create_book_owned_by_themselves(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->make();
        $token = $user->createToken('book-api')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/books', [
            ...$this->payload($book, $genre),
            'user_id' => $otherUser->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('books', [
            'isbn' => $book->isbn,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('books', [
            'isbn' => $book->isbn,
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_owner_can_update_and_delete_book_with_bearer_token(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $genre = Genre::factory()->create();
        $token = $owner->createToken('book-api')->plainTextToken;
        $payload = $this->payload($book, $genre);
        $payload['title'] = '所有者による更新';

        $this->withToken($token)
            ->putJson("/api/v1/books/{$book->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.title', '所有者による更新');

        $this->withToken($token)
            ->deleteJson("/api/v1/books/{$book->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_non_owner_cannot_update_or_delete_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->for($owner)->create(['title' => '変更前']);
        $genre = Genre::factory()->create();
        $token = $otherUser->createToken('book-api')->plainTextToken;
        $payload = $this->payload($book, $genre);
        $payload['title'] = '不正な更新';

        $responses = [
            $this->withToken($token)->putJson("/api/v1/books/{$book->id}", $payload),
            $this->withToken($token)->deleteJson("/api/v1/books/{$book->id}"),
        ];

        foreach ($responses as $response) {
            $response->assertForbidden()
                ->assertHeader('content-type', 'application/json')
                ->assertExactJson(['error' => 'この操作を行う権限がありません。']);
        }

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '変更前',
        ]);
    }

    private function payload(Book $book, Genre $genre): array
    {
        return [
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => $book->isbn,
            'published_date' => '2025-01-01',
            'description' => $book->description,
            'image_url' => $book->image_url,
            'genres' => [$genre->id],
        ];
    }
}
