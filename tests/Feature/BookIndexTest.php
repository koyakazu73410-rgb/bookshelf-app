<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BookIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_index_can_be_displayed(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_book_title_is_displayed_on_index(): void
    {
        $book = Book::factory()->create([
            'title' => 'PHP入門',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);

        $response->assertSee('PHP入門');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
