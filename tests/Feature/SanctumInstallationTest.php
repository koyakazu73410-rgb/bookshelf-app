<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Tests\TestCase;

class SanctumInstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_configuration_and_database_table_are_available(): void
    {
        $this->assertSame(['web'], config('sanctum.guard'));
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
        $this->assertContains(HasApiTokens::class, class_uses_recursive(User::class));
    }

    public function test_user_can_issue_a_sanctum_personal_access_token(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token');

        $this->assertNotEmpty($token->plainTextToken);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'test-token',
        ]);
    }

    public function test_guest_can_access_existing_public_book_read_api(): void
    {
        $book = Book::factory()->create();

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonFragment(['id' => $book->id]);

        $this->getJson("/api/v1/books/{$book->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $book->id]);
    }

    public function test_guest_cannot_access_protected_book_write_api(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->make();

        $response = $this->postJson('/api/v1/books', [
            'user_id' => $user->id,
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => $book->isbn,
            'published_date' => '2025-01-01',
            'description' => $book->description,
            'image_url' => $book->image_url,
            'genres' => [$genre->id],
        ]);

        $response->assertUnauthorized();
        $this->assertDatabaseMissing('books', ['isbn' => $book->isbn]);
    }
}
