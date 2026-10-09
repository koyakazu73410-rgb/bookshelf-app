<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BookStoreTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_authenticated_user_can_store_book(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $this->actingAs($user);
        $data = [
            'title' => 'PHP入門',
            'author' => '山田太郎',
            'isbn' => '9781234567890',
            'published_date' => '2016-10-03',
            'description' => 'PHPを学ぶための入門書です。',
            'genres' => [$genre->id],
        ];

        $response = $this->post('/books', $data);

        $book = Book::where('title', 'PHP入門')->first();

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'title' => 'PHP入門',
            'author' => '山田太郎',
            'isbn' => '9781234567890',
        ]);
    }
}
