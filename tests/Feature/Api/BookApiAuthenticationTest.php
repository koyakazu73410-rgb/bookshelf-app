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
                ->assertExactJson(['message' => 'Unauthenticated.']);
        }
    }

    public function test_invalid_bearer_token_cannot_access_book_write_endpoints(): void
    {
        $owner = User::factory()->create();
        $existingBook = Book::factory()->for($owner)->create([
            'title' => '変更前の書籍',
        ]);
        $genre = Genre::factory()->create();
        $newBook = Book::factory()->make();
        $invalidToken = 'invalid-bearer-token';

        $createPayload = $this->payload($newBook, $genre);
        $updatePayload = $this->payload($existingBook, $genre);
        $updatePayload['title'] = '不正な更新';

        $responses = [
            $this->withToken($invalidToken)->postJson('/api/v1/books', $createPayload),
            $this->withToken($invalidToken)->putJson("/api/v1/books/{$existingBook->id}", $updatePayload),
            $this->withToken($invalidToken)->deleteJson("/api/v1/books/{$existingBook->id}"),
        ];

        foreach ($responses as $response) {
            $response->assertUnauthorized()
                ->assertHeader('content-type', 'application/json')
                ->assertExactJson(['message' => 'Unauthenticated.']);
        }

        $this->assertDatabaseMissing('books', [
            'isbn' => $newBook->isbn,
        ]);
        $this->assertDatabaseHas('books', [
            'id' => $existingBook->id,
            'title' => '変更前の書籍',
        ]);
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

    public function test_user_id_in_update_request_does_not_change_book_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->for($owner)->create();
        $genre = Genre::factory()->create();
        $token = $owner->createToken('book-api')->plainTextToken;
        $payload = $this->payload($book, $genre);
        $payload['title'] = '所有者を維持する更新';
        $payload['user_id'] = $otherUser->id;

        $this->withToken($token)
            ->putJson("/api/v1/books/{$book->id}", $payload)
            ->assertOk();

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '所有者を維持する更新',
            'user_id' => $owner->id,
        ]);
        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
            'user_id' => $otherUser->id,
        ]);
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
                ->assertExactJson(['error' => 'この操作を実行する権限がありません。']);
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
