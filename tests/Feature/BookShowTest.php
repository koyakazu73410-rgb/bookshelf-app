<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BookShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_detail_can_be_displayed(): void
    {
        $this->withoutVite();

        $book = Book::factory()->create([
            'title' => 'PHP入門',
        ]);

        $this->withoutExceptionHandling();

        $response = $this->get(route('books.show', $book));

        $response->assertStatus(200);

        $response->assertSee('PHP入門');
    }
}
