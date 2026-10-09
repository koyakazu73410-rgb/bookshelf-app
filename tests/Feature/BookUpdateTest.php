<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BookUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_own_book(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $book = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'PHP入門',
        ]);

        $genre = Genre::factory()->create();

        $data = [
            'title' => 'Laravel入門',
            'author' => '山田太郎',
            'isbn' => '9781234567890',
            'published_date' => '2015-01-01',
            'genres' => [$genre->id],
        ];

        $response = $this->put(
            route('books.update', $book),
            $data
        );

        $response->assertSessionHasNoErrors();

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'title' => 'Laravel入門',
            'author' => '山田太郎',
            'isbn' => '9781234567890',
        ]);
    }
}
